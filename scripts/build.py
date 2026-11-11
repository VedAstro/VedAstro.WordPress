"""Build a WordPress-installable ZIP containing only distributable files."""
import argparse
from pathlib import Path
import re
from zipfile import ZipFile, ZIP_DEFLATED

parser = argparse.ArgumentParser()
parser.add_argument('--version', default='1.0.0')
args = parser.parse_args()
if not re.fullmatch(r'\d+\.\d+\.\d+', args.version):
    parser.error('version must be major.minor.patch')
root = Path(__file__).resolve().parent.parent
output = root / 'dist' / 'vedastro.zip'
output.parent.mkdir(exist_ok=True)
files = [root / name for name in ('vedastro.php', 'uninstall.php', 'readme.txt', 'README.md', 'LICENSE')]
for directory in ('includes', 'assets'):
    files.extend(p for p in (root / directory).rglob('*') if p.is_file())
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for path in sorted(files):
        data = path.read_text(encoding='utf-8')
        if path.name == 'vedastro.php':
            data = re.sub(r'(?m)( \* Version: )\S+', lambda m: m[1] + args.version, data)
            data = re.sub(r"define\('VEDASTRO_VERSION', '[^']+'\)", f"define('VEDASTRO_VERSION', '{args.version}')", data)
        if path.name == 'readme.txt':
            data = re.sub(r'(?m)^Stable tag: .*$', 'Stable tag: ' + args.version, data)
        if path.name == 'README.md':
            # Keep the installed ZIP small while preserving documentation links.
            data = data.replace('src="docs/', 'src="https://raw.githubusercontent.com/VedAstro/VedAstro.WordPress/main/docs/')
            data = data.replace('](docs/images/', '](https://raw.githubusercontent.com/VedAstro/VedAstro.WordPress/main/docs/images/')
            data = data.replace('](docs/', '](https://github.com/VedAstro/VedAstro.WordPress/blob/main/docs/')
        archive.writestr('vedastro/' + path.relative_to(root).as_posix(), data.encode('utf-8'))
with ZipFile(output) as archive:
    assert archive.testzip() is None
    assert 'vedastro/vedastro.php' in archive.namelist()
    assert all(name.startswith('vedastro/') for name in archive.namelist())
print(output)
