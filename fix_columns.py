import re

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'r') as f:
    content = f.read()

# Replace stage.source_financement.nom
content = content.replace("accessorKey: 'stage.source_financement.nom',", "id: 'source_financement',\n                accessorFn: (row: any) => row.stage?.source_financement?.nom,")

# Replace stage.type_stage.nom
content = content.replace("accessorKey: 'stage.type_stage.nom',", "id: 'type_stage',\n                accessorFn: (row: any) => row.stage?.type_stage?.nom,")

# Replace stage.entreprise.type_structure.nom
content = content.replace("accessorKey: 'stage.entreprise.type_structure.nom',", "id: 'type_structure',\n                accessorFn: (row: any) => row.stage?.entreprise?.type_structure?.nom,")

# Let's also check others like stage.entreprise.raison_sociale
content = content.replace("accessorKey: 'stage.entreprise.raison_sociale',", "id: 'entreprise',\n                accessorFn: (row: any) => row.stage?.entreprise?.raison_sociale,")

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'w') as f:
    f.write(content)
