from pathlib import Path
import re

files = {
    'blog-mejores-auriculares-inalambricos.html': 'Auriculares',
    'blog-como-elegir-reloj-inteligente.html': 'Relojes',
    'blog-auriculares-vs-cascos-gaming.html': 'Gaming',
    'blog-como-elegir-funda-movil.html': 'Móviles',
    'blog-smartwatch-barato-espana.html': 'Smartwatch',
    'blog-como-conectar-auriculares-bluetooth.html': 'Bluetooth',
    'blog-como-limpiar-auriculares-inalambricos.html': 'Cuidado',
    'blog-mejores-auriculares-deporte.html': 'Deporte',
    'blog-alargar-bateria-smartwatch.html': 'Batería',
    'blog-como-elegir-raton-inalambrico.html': 'Ratón',
    'blog-manos-libres-coche.html': 'Coche',
}

for name, eyebrow in files.items():
    path = Path(name)
    if not path.exists():
        print('MISSING', name)
        continue
    text = path.read_text(encoding='utf-8')
    if 'blog-breadcrumb' in text:
        print('SKIP already patched', name)
        continue

    def replacement(match):
        h1_text = match.group(3)
        return (
            match.group(1)
            + '        <nav class="blog-breadcrumb" aria-label="Migas de pan">\n'
            + '            <a href="index.html">Inicio</a>\n'
            + '            <i class="fas fa-chevron-right"></i>\n'
            + '            <a href="blog.html">Blog</a>\n'
            + '            <i class="fas fa-chevron-right"></i>\n'
            + '            <span class="current">' + h1_text + '</span>\n'
            + '        </nav>\n\n'
            + '        <span class="blog-eyebrow">' + eyebrow + '</span>\n'
            + '        <h1 class="blog-article-title">' + h1_text + '</h1>'
        )

    new_text, count = re.subn(
        r'(<article class="blog-post">\s*\n\s*)(<h1>)(.*?)(</h1>)',
        replacement,
        text,
        count=1,
        flags=re.S,
    )
    if count != 1:
        print('NO MATCH header', name)
        continue

    meta = (
        '\n\n        <div class="blog-meta-row">\n'
        '            <div class="blog-author-block">\n'
        '                <div class="blog-author-avatar">EK</div>\n'
        '                <div>\n'
        '                    <div class="blog-author-name">Equipo de KhurmiStore</div>\n'
        '                    <div class="blog-author-role">Redacción de KhurmiStore</div>\n'
        '                </div>\n'
        '            </div>\n'
        '            <span><i class="far fa-calendar"></i> 4 de junio de 2026</span>\n'
        '            <span><i class="far fa-clock"></i> 5 min de lectura</span>\n'
        '        </div>\n\n'
        '        <div class="blog-body">\n\n'
    )
    new_text2, count2 = re.subn(r'(</h1>\s*\n)(\s*<p>)', r'\1' + meta + r'\2', new_text, count=1)
    if count2 != 1:
        print('NO MATCH body open', name)
        continue

    new_text3, count3 = re.subn(r'\n(\s*)(</article>)', r'\n\1</div>\n\1\2', new_text2, count=1)
    if count3 != 1:
        print('NO MATCH closing article', name)
        continue

    path.write_text(new_text3, encoding='utf-8')
    print('PATCHED', name)
