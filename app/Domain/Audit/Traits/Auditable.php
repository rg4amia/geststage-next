<?php

namespace App\Domain\Audit\Traits;

use App\Domain\Audit\Support\AuditContext;
use App\Models\Audit\JournalAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Boot the trait.
     */
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::audit('created', $model);
        });

        static::updated(function ($model) {
            static::audit('updated', $model);
        });

        static::deleted(function ($model) {
            static::audit('deleted', $model);
        });
    }

    /**
     * Enregistrer l'action dans le journal d'audit.
     */
    protected static function audit(string $action, Model $model): void
    {
        if (AuditContext::isSuppressed()) {
            return;
        }

        $anciennesDonnees = null;
        $nouvellesDonnees = null;

        if ($action === 'created') {
            $nouvellesDonnees = $model->getAttributes();
        } elseif ($action === 'updated') {
            $anciennesDonnees = array_intersect_key($model->getOriginal(), $model->getChanges());
            $nouvellesDonnees = $model->getChanges();
        } elseif ($action === 'deleted') {
            $anciennesDonnees = $model->getAttributes();
        }

        JournalAudit::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'modele_type' => get_class($model),
            'modele_id' => $model->getKey(),
            'anciennes_donnees' => empty($anciennesDonnees) ? null : $anciennesDonnees,
            'nouvelles_donnees' => empty($nouvellesDonnees) ? null : $nouvellesDonnees,
            'adresse_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
