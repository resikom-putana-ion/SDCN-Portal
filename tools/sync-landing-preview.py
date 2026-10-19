"""Refresh the private preview from a reviewed checkout of the landing repository."""
import argparse
from pathlib import Path
import re
import shutil

parser = argparse.ArgumentParser()
parser.add_argument('source', type=Path, help='Path to the landing-page checkout')
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
landing = args.source.resolve()
if not (landing / 'resources/views/layouts/site.blade.php').is_file():
    parser.error('The source must contain resources/views/layouts/site.blade.php')

dest = root / 'resources/views/website/site'
components = root / 'resources/views/components/landing-preview'
(dest / 'pages').mkdir(parents=True, exist_ok=True)
components.mkdir(parents=True, exist_ok=True)
sources = [(landing / 'resources/views/layouts/site.blade.php', dest / 'layout.blade.php')]
sources += [(p, dest / 'pages' / p.name) for p in (landing / 'resources/views/pages').glob('*.blade.php') if p.name != 'announcement.blade.php']
sources += [(p, components / p.name) for p in (landing / 'resources/views/components').glob('*.blade.php')]
for source, target in sources:
    text = source.read_text(encoding='utf-8').replace("'layouts.site'", "'website.site.layout'")
    text = text.replace("@include('partials.errors')", '')
    text = re.sub(r'<(/?)x-([a-z][a-z-]*)', r'<\1x-landing-preview.\2', text)
    text = text.replace('route(', '\\App\\Services\\LandingPreview::url(')
    text = text.replace('/assets/', '/landing-preview/assets/').replace('/css/', '/landing-preview/css/').replace('/js/', '/landing-preview/js/')
    text = text.replace('/landing-preview/assets/fonts/Poppins-', '/assets/Poppins-')
    text = text.replace('/assets/Poppins-Bold.ttf', '/landing-preview/assets/fonts/Poppins-Bold.ttf')
    # Uploaded images in an unsaved preview are server-validated data URLs.
    # Public components accept local paths only; the private preview also accepts
    # these raster data URLs and the remapped bundled asset paths.
    if target.name in ['person-photo.blade.php', 'staff-portrait.blade.php']:
        text = text.replace("preg_match('#^/(?:assets/(?:design|team)|media)/[a-zA-Z0-9_-]+\\.(png|jpe?g|webp)$#', $photo)",
                            "\\App\\Services\\LandingPreview::validPhoto($photo)")
    if target == dest / 'layout.blade.php':
        text = text.replace('</body>', '<script type="application/json" id="preview-blocks">{!! json_encode($previewBlocks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>\n<script src="/js/website-preview.js" defer></script>\n<link rel="stylesheet" href="/css/website-preview.css">\n</body>')
    target.write_text(text, encoding='utf-8')

assets = root / 'public/landing-preview'
for category, names in {
    'css':['site.css','appearance.css','hero-motion.css','announcement.css','team-directory.css','registration.css'],
    'js':['site.js','language.js','team-directory.js'],
}.items():
    (assets / category).mkdir(parents=True, exist_ok=True)
    for name in names:
        text = (landing / 'public' / category / name).read_text(encoding='utf-8')
        text = text.replace('/assets/', '/landing-preview/assets/').replace('/landing-preview/assets/fonts/Poppins-', '/assets/Poppins-')
        text = text.replace('/assets/Poppins-Bold.ttf', '/landing-preview/assets/fonts/Poppins-Bold.ttf')
        (assets / category / name).write_text(text, encoding='utf-8')
for category in ['design', 'team']:
    shutil.copytree(landing / 'public/assets' / category, assets / 'assets' / category, dirs_exist_ok=True)
(assets / 'assets/fonts').mkdir(parents=True, exist_ok=True)
shutil.copy2(landing / 'public/assets/fonts/Poppins-Bold.ttf', assets / 'assets/fonts/Poppins-Bold.ttf')
print('Landing preview refreshed. Run the editor feature and browser checks before publishing code.')
