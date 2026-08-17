import re

with open('resources/js/velzone/pages/Inscriptions/Show.tsx', 'r') as f:
    content = f.read()

old_historique_block = """                                    <div className="profile-timeline">
                                        <div className="accordion accordion-flush" id="accordionFlushExample">
                                            {evenements && evenements.length > 0 ? evenements.map((evt: any, idx: number) => (
                                                <div className="accordion-item border-0" key={idx}>
                                                    <div className="accordion-header" id={`heading${idx}`}>
                                                        <a className="accordion-button p-2 shadow-none" data-bs-toggle="collapse" href={`#collapse${idx}`} aria-expanded="true">
                                                            <div className="d-flex align-items-center">
                                                                <div className="flex-shrink-0 avatar-xs">
                                                                    <div className="avatar-title bg-success rounded-circle">
                                                                        <i className="ri-check-line"></i>
                                                                    </div>
                                                                </div>
                                                                <div className="flex-grow-1 ms-3">
                                                                    <h6 className="fs-14 mb-0 fw-semibold">{evt.action || 'Transition'}</h6>
                                                                </div>
                                                            </div>
                                                        </a>
                                                    </div>
                                                    <div id={`collapse${idx}`} className="accordion-collapse collapse show" aria-labelledby={`heading${idx}`} data-bs-parent="#accordionExample">
                                                        <div className="accordion-body ms-2 ps-5 pt-0">
                                                            <h6 className="mb-1">{evt.acteur?.nom} {evt.acteur?.prenom}</h6>
                                                            <p className="text-muted mb-0">{formatDateTime(evt.survenu_le)}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            )) : ("""

new_historique_block = """                                    <div className="profile-timeline">
                                        <div className="accordion accordion-flush" id="accordionFlushExample">
                                            {evenements && evenements.length > 0 ? evenements.map((evt: any, idx: number) => {
                                                const sourceName = evt.etape_source?.nom || evt.etapeSource?.nom;
                                                const cibleName = evt.etape_cible?.nom || evt.etapeCible?.nom;
                                                const message = evt.donnees?.message || evt.donnees?.commentaire;
                                                
                                                return (
                                                <div className="accordion-item border-0" key={idx}>
                                                    <div className="accordion-header" id={`heading${idx}`}>
                                                        <a className="accordion-button p-2 shadow-none" data-bs-toggle="collapse" href={`#collapse${idx}`} aria-expanded="true">
                                                            <div className="d-flex align-items-center">
                                                                <div className="flex-shrink-0 avatar-xs">
                                                                    <div className="avatar-title bg-success rounded-circle">
                                                                        <i className="ri-check-line"></i>
                                                                    </div>
                                                                </div>
                                                                <div className="flex-grow-1 ms-3">
                                                                    <h6 className="fs-14 mb-1 fw-semibold">
                                                                        {cibleName ? `Passage à : ${cibleName}` : (evt.action || 'Mise à jour du dossier')}
                                                                    </h6>
                                                                    <small className="text-muted">{formatDateTime(evt.survenu_le)}</small>
                                                                </div>
                                                            </div>
                                                        </a>
                                                    </div>
                                                    <div id={`collapse${idx}`} className="accordion-collapse collapse show" aria-labelledby={`heading${idx}`} data-bs-parent="#accordionExample">
                                                        <div className="accordion-body ms-2 ps-5 pt-0">
                                                            <div className="mb-2">
                                                                <span className="fw-medium">Par : </span> {evt.acteur?.nom} {evt.acteur?.prenoms || evt.acteur?.prenom}
                                                            </div>
                                                            {sourceName && (
                                                                <div className="mb-2">
                                                                    <span className="fw-medium">Étape précédente : </span> <span className="badge bg-light text-body">{sourceName}</span>
                                                                </div>
                                                            )}
                                                            {message && (
                                                                <div className="mt-2 p-2 bg-light rounded">
                                                                    <i className="ri-message-2-line text-muted me-2"></i>
                                                                    <span className="text-muted fst-italic">{message}</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            )}) : ("""

content = content.replace(old_historique_block, new_historique_block)

with open('resources/js/velzone/pages/Inscriptions/Show.tsx', 'w') as f:
    f.write(content)
