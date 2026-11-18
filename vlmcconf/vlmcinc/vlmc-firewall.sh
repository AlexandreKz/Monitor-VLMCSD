#!/usr/bin/env bash
# ============================================
# ФАЙЛ: vlmcinc/vlmc-firewall.sh
# ВЕРСИЯ: 1.0.0
# ДАТА: 2026-11-18
# @description: Помощник блокировки IP для панели KMS Monitor (п. 30). Запускается через sudo от имени веб-сервера.
#
# Установка (один раз, от root):
#   install -m 750 -o root -g root /var/www/html/vlmcconf/vlmcinc/vlmc-firewall.sh /usr/local/sbin/vlmc-firewall
#   echo 'www-data ALL=(root) NOPASSWD: /usr/local/sbin/vlmc-firewall' > /etc/sudoers.d/vlmc-firewall
#   chmod 440 /etc/sudoers.d/vlmc-firewall
#
# Использование:
#   vlmc-firewall status <auto|ufw|iptables|nftables>
#   vlmc-firewall <auto|ufw|iptables|nftables> <block|unblock> <ip> [порт]
# Скрипт принимает только корректный IP и порт и ничего другого не исполняет.
# Для проверки без изменений: VLMC_FW_DRY_RUN=1 (команды только печатаются).
# ============================================
set -u
PATH=/usr/sbin:/usr/bin:/sbin:/bin

DRY="${VLMC_FW_DRY_RUN:-0}"
COMMENT="vlmc-monitor"
NFT_TABLE="vlmc_monitor"

fail() { echo "$*" >&2; exit 1; }

valid_ip() {
  local ip="$1"
  if [[ "$ip" =~ ^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$ ]]; then
    local i
    for i in 1 2 3 4; do [ "${BASH_REMATCH[$i]}" -le 255 ] || return 1; done
    return 0
  fi
  # IPv6: только шестнадцатеричные цифры и двоеточия, минимум два двоеточия
  [[ "$ip" =~ ^[0-9A-Fa-f:]+$ ]] && [[ "$ip" == *:*:* ]] && [ "${#ip}" -le 39 ]
}

run() {
  if [ "$DRY" = "1" ]; then echo "DRY: $*"; return 0; fi
  "$@"
}

detect() {
  local want="$1"
  if [ "$want" != "auto" ]; then
    local bin="$want"; [ "$want" = "nftables" ] && bin="nft"
    command -v "$bin" >/dev/null 2>&1 && { echo "$want"; return 0; }
    return 1
  fi
  if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | head -1 | grep -qi "active"; then echo ufw; return 0; fi
  if command -v nft >/dev/null 2>&1; then echo nftables; return 0; fi
  if command -v iptables >/dev/null 2>&1; then echo iptables; return 0; fi
  return 1
}

resolve_cmd() {
  case "$1" in
    nftables) command -v nft ;;
    *) command -v "$1" ;;
  esac
}

[ $# -ge 2 ] || fail "usage: $0 status <backend> | <backend> <block|unblock> <ip> [port]"

if [ "$1" = "status" ]; then
  b="$(detect "$2")" || fail "no firewall backend found"
  resolve_cmd "$b" >/dev/null || fail "no firewall backend found"
  echo "ok $b"
  exit 0
fi

backend_req="$1"; action="$2"; ip="${3:-}"; port="${4:-}"
case "$backend_req" in auto|ufw|iptables|nftables) ;; *) fail "bad backend" ;; esac
case "$action" in block|unblock) ;; *) fail "bad action" ;; esac
valid_ip "$ip" || fail "bad ip"
if [ -n "$port" ]; then
  [[ "$port" =~ ^[0-9]{1,5}$ ]] && [ "$port" -ge 1 ] && [ "$port" -le 65535 ] || fail "bad port"
fi
b="$(detect "$backend_req")" || fail "no firewall backend found"

v6=0; [[ "$ip" == *:* ]] && v6=1

case "$b" in
  ufw)
    if [ "$action" = "block" ]; then
      if [ -n "$port" ]; then run ufw insert 1 deny proto tcp from "$ip" to any port "$port" comment "$COMMENT"
      else run ufw insert 1 deny from "$ip" comment "$COMMENT"; fi
    else
      if [ -n "$port" ]; then run ufw --force delete deny proto tcp from "$ip" to any port "$port"
      else run ufw --force delete deny from "$ip"; fi
    fi ;;
  iptables)
    ipt=iptables; [ "$v6" = 1 ] && ipt=ip6tables
    command -v "$ipt" >/dev/null 2>&1 || fail "$ipt not found"
    args=(-s "$ip")
    [ -n "$port" ] && args+=(-p tcp --dport "$port")
    args+=(-m comment --comment "$COMMENT" -j DROP)
    if [ "$action" = "block" ]; then
      "$ipt" -C INPUT "${args[@]}" 2>/dev/null && { echo "already blocked"; exit 0; }
      run "$ipt" -I INPUT 1 "${args[@]}"
    else
      run "$ipt" -D INPUT "${args[@]}"
    fi ;;
  nftables)
    fam=ip; elem=ip; [ "$v6" = 1 ] && { fam=ip6; elem=ip6; }
    set_name="blocked4"; [ "$v6" = 1 ] && set_name="blocked6"
    if [ "$action" = "block" ]; then
      run nft add table inet "$NFT_TABLE"
      run nft "add set inet $NFT_TABLE $set_name { type ${fam}_addr; flags interval; }" 2>/dev/null || true
      run nft "add chain inet $NFT_TABLE input { type filter hook input priority -10; policy accept; }" 2>/dev/null || true
      # правило-ссылка на набор добавляется один раз (на весь набор: с портом или без)
      if [ "$DRY" = "1" ] || ! nft list chain inet "$NFT_TABLE" input 2>/dev/null | grep -q "@$set_name"; then
        if [ -n "$port" ]; then
          run nft "add rule inet $NFT_TABLE input $elem saddr @$set_name tcp dport $port drop"
        else
          run nft "add rule inet $NFT_TABLE input $elem saddr @$set_name drop"
        fi
      fi
      run nft "add element inet $NFT_TABLE $set_name { $ip }"
    else
      run nft "delete element inet $NFT_TABLE $set_name { $ip }"
    fi ;;
esac
