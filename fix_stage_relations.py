import re

with open('app/Models/Internship/Stage.php', 'r') as f:
    content = f.read()

# Add use statements if needed
if r"use App\Models\Reference\Programme;" not in content:
    content = content.replace(r"use App\Models\Reference\TypeStage;", "use App\Models\Reference\TypeStage;\nuse App\Models\Reference\Programme;")

# Add relations
relations = """
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }
"""

if "public function programme" not in content:
    content = content.replace("public function typeStage(): BelongsTo\n    {\n        return $this->belongsTo(TypeStage::class, 'type_stage_id');\n    }", "public function typeStage(): BelongsTo\n    {\n        return $this->belongsTo(TypeStage::class, 'type_stage_id');\n    }\n" + relations)

with open('app/Models/Internship/Stage.php', 'w') as f:
    f.write(content)
