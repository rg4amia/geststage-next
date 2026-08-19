<div>
    <div style="-aw-headerfooter-type:header-primary; clear:both">
        <p class="Header" style="text-align:center">
            <span>
                <img src="{{ url('contrat-edition/img/head-pejedec.png') }}" alt="" width="650" />
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
    <!-- Container for centered content -->
    <div style="display: flex; justify-content: center; align-items: center; height: 100vh; position: absolute; top: 50%; left: 60%; transform: translate(-50%, 0%); width: 100%;">
        <img src="{{ url('contrat-edition/img/titre-pejedec.png') }}" alt="" style="width: 80%;">
    </div>

    <div style="position: fixed; bottom: 0; width: 100%; background-color: white;">
        <img src="{{ url('contrat-edition/img/psgouv_footer.png') }}" alt="" style="width: 100%;">
    </div>

    <div style="page-break-before: always;"></div>
</div>
