<div>
    <div style="-aw-headerfooter-type:header-primary; clear:both">
        <p class="Header" style="text-align:center">
            <span>
                <img src="{{ url('contrat-edition/img/head-pnjs2.png') }}" alt="" width="650" />
            </span>
        </p>
    </div>

    <p style="margin-bottom:20px; line-height:normal" class="declaration-text">
        <span style="font-weight:bold">
            N/Réf.___________/ ADM / DOP / AR –
            {{ Illuminate\Support\Str::limit($agence->libelle_agence, 3) }} /
             {{ getInitials($agence->chef_agence) }} / {{ getInitials($conseiller) }} / {{ \Carbon\Carbon::now()->format("m - Y")}}
        </span>
    </p>

    <div
        style="display: flex; justify-content: center; align-items: center; height: 100vh; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 100%;">
        <div style="background-color: #3ea446; border-radius: 25px; padding: 10px 0; text-align: center; color: white; font-family: Arial; font-weight: bold; border: 2px solid #cb9048;">
            <p style="font-size: 30px; line-height: 1.5; letter-spacing: 0.5px;">PROGRAMME DE STAGE<br> DE VALIDATION</p>
            <p style="font-size: 22px; margin: 0;">CONTRAT</p>
        </div>
    </div>

    <div style="position: fixed; bottom: 0; width: 100%; background-color: white;">
        <img src="{{ url('contrat-edition/img/psgouv_footer.png') }}" alt="" style="width: 100%;">
    </div>

    <div style="page-break-before: always;"></div>
</div>
