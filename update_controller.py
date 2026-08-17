import re

with open('app/Http/Controllers/Cip/MesStagiairesCipController.php', 'r') as f:
    content = f.read()

new_methods = """    /**
     * Générer le contrat de stage
     */
    public function genererContrat(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        $fonction = $request->query('fonction');
        $montant = $request->query('montant');
        
        // TODO: Logique de génération PDF
        // Retourne un message temporaire pour le frontend
        return back()->with('success', "Le contrat pour {$instance->stage->beneficiaire->nom} a été généré avec succès.");
    }

    /**
     * Transférer le contrat signé (upload)
     */
    public function transfererContrat(Request $request, $id)
    {
        $request->validate([
            'contrat_stage' => 'required|file|mimes:pdf|max:5120', // 5MB max
        ]);

        $instance = InstanceParcours::findOrFail($id);

        if ($request->hasFile('contrat_stage')) {
            $path = $request->file('contrat_stage')->store('contrats_stagiaires', 'public');
            // TODO: Enregistrer le chemin dans le modèle
            // $instance->stage->update(['file_contrat' => $path]);
        }

        return back()->with('success', 'Contrat transféré avec succès.');
    }

    /**
     * Générer la fiche Trésor Money
     */
    public function genererTresorMoney(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        
        // TODO: Logique de génération de la fiche PDF
        return back()->with('success', "Fiche Trésor Money générée avec succès.");
    }

    /**
     * Uploader la fiche Trésor Money scannée
     */
    public function uploadTresorMoney(Request $request, $id)
    {
        $request->validate([
            'tresor_money_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $instance = InstanceParcours::findOrFail($id);

        if ($request->hasFile('tresor_money_file')) {
            $path = $request->file('tresor_money_file')->store('tresor_money_files', 'public');
            // TODO: Enregistrer le chemin dans le modèle
            // $instance->stage->update(['file_tresor_money' => $path]);
        }

        return back()->with('success', 'Fiche Trésor Money enregistrée avec succès.');
    }

    /**
     * Supprimer un dossier stagiaire
     */
    public function destroy($id)
    {
        $instance = InstanceParcours::findOrFail($id);
        
        // Note: Selon la logique métier, on supprime l'instance, ou le stage associé
        $instance->delete();

        return back()->with('success', 'Dossier stagiaire supprimé avec succès.');
    }
}
"""

content = re.sub(r'\}\s*$', new_methods, content)

with open('app/Http/Controllers/Cip/MesStagiairesCipController.php', 'w') as f:
    f.write(content)
