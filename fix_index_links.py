import re

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'r') as f:
    content = f.read()

# Fix Détails href
content = content.replace('href={`/cip/mes-stagiaires/${row.id}`}', 'href={`/inscriptions/${row.id}`}')

# Fix Modifier href
content = content.replace('href={`/cip/mes-stagiaires/${row.id}/edit`}', 'href={`/inscriptions/${row.id}/edit`}')

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'w') as f:
    f.write(content)
