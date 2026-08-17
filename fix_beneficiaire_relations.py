import re

with open('app/Models/Beneficiary/Beneficiaire.php', 'r') as f:
    content = f.read()

# Add use statements if needed
if r"use App\Models\Reference\NiveauEtude;" not in content:
    content = content.replace(r"use App\Models\Reference\TypePaiement;", "use App\Models\Reference\TypePaiement;\nuse App\Models\Reference\NiveauEtude;\nuse App\Models\Reference\Handicap;\nuse App\Models\Reference\TypeHandicap;")

# Add relations
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
