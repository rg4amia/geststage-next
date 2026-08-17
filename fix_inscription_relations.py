import re

with open('app/Http/Controllers/Registration/InscriptionController.php', 'r') as f:
    content = f.read()

relations_to_add = """            'stage.beneficiaire.typePaiement',
            'stage.beneficiaire.niveauEtude',
            'stage.beneficiaire.handicap',
            'stage.beneficiaire.typeHandicap',
            'stage.typeStage',
            'stage.sourceFinancement',
            'stage.programme',"""

# We'll replace the first relation with the same relation + our new ones
content = content.replace("'stage.beneficiaire.communeResidence',", "'stage.beneficiaire.communeResidence',\n" + relations_to_add)

with open('app/Http/Controllers/Registration/InscriptionController.php', 'w') as f:
    f.write(content)
