import re

with open('resources/js/velzone/pages/Inscriptions/Show.tsx', 'r') as f:
    content = f.read()

# Fix Modifier href
content = content.replace('href={`/cip/mes-stagiaires/${instance.id}/edit`}', 'href={`/inscriptions/${instance.id}/edit`}')

with open('resources/js/velzone/pages/Inscriptions/Show.tsx', 'w') as f:
    f.write(content)
