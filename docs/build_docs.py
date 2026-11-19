#!/usr/bin/env python3
# Генератор: docs/*.md и docs/en/*.md -> vlmcconf/sections/doc_desc.php
# Запуск из корня проекта: python3 docs/build_docs.py  (нужен модуль markdown)
import glob, json, os, re, datetime
import markdown

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ICONS = {'00': '🏠', '01': '⚙️', '02': '👥', '03': '📱', '04': '🔒', '05': '🛠️',
         '06': '🔔', '07': '🔌', '08': '🖥️', '09': '📊', '10': '📜', '11': 'ℹ️', '12': '🔑'}

def build(folder):
    out = []
    for f in sorted(glob.glob(os.path.join(folder, '[0-9][0-9]-*.md'))):
        n = os.path.basename(f)[:2]
        text = open(f, encoding='utf-8').read()
        title = re.match(r'# (.+)', text).group(1).strip()
        body = re.sub(r'^# .+\n', '', text, count=1)
        html = markdown.markdown(body, extensions=['tables', 'fenced_code'])
        html = re.sub(r'href="(\d\d)-[^"]*\.md"', r'href="#" data-doc="d\1"', html)
        out.append({'id': 'd' + n, 'icon': ICONS.get(n, '📄'), 'title': title, 'html': html})
    return out

data = {'ru': build(os.path.join(ROOT, 'docs')), 'en': build(os.path.join(ROOT, 'docs', 'en'))}
js = json.dumps(data, ensure_ascii=False)
assert 'JSON_DOC_END' not in js
php = """<?php
// ============================================
// ФАЙЛ: sections/doc_desc.php
// ВЕРСИЯ: 1.0.0
// ДАТА: %s
// @description: Описание разделов и опций панели (RU/EN). СГЕНЕРИРОВАН из docs/*.md скриптом docs/build_docs.py, вручную не править
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'doc_desc.php') {
    http_response_code(403);
    exit('Access denied');
}

return json_decode(<<<'JSON_DOC_END'
%s
JSON_DOC_END
, true);
""" % (datetime.date.today().isoformat(), js)
dst = os.path.join(ROOT, 'vlmcconf', 'sections', 'doc_desc.php')
open(dst, 'w', encoding='utf-8', newline='\n').write(php)
print('ok', {k: len(v) for k, v in data.items()}, os.path.getsize(dst))
