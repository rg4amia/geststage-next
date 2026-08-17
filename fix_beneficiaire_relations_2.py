import re

with open('app/Models/Beneficiary/Beneficiaire.php', 'r') as f:
    content = f.read()

relations = """
    public function niveauEtude(): BelongsTo
    {
        return $this->belongsTo(NiveauEtude::class, 'niveau_etude_id');
    }

    public function handicap(): BelongsTo
    {
        return $this->belongsTo(Handicap::class, 'handicap_id');
    }

    public function typeHandicap(): BelongsTo
    {
        return $this->belongsTo(TypeHandicap::class, 'type_handicap_id');
    }
"""

if "public function niveauEtude" not in content:
    content = content.replace("public function typePaiement(): BelongsTo\n    {\n        return $this->belongsTo(TypePaiement::class, 'type_paiement_id');\n    }", "public function typePaiement(): BelongsTo\n    {\n        return $this->belongsTo(TypePaiement::class, 'type_paiement_id');\n    }\n" + relations)

with open('app/Models/Beneficiary/Beneficiaire.php', 'w') as f:
    f.write(content)
