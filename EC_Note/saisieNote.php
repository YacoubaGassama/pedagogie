<?php
session_start();

// Vérifier si l'utilisateur est connecté
// if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
//     header('Location: http://localhost/pedagogie/page-connexion');
//     exit;
// }

// // Vérifier si le statut de l'utilisateur est "Error"
// if (isset($_SESSION['current_user'][0]['statut']) && $_SESSION['current_user'][0]['statut'] === 'Error' && $_SESSION['statutUtilisateur'] == 0) {
//     $msg = $_SESSION['current_user'][0]['Message'];
//     $_SESSION['alert_message'] = json_encode([
//         'title' => 'Token expiré',
//         'text' => "$msg\nVous n'avez pas accés à cette page.",
//         'icon' => 'error',
//         'confirmButtonText' => 'OK'
//     ]);
//     header('Location: nonAccessPage.php');
//     exit;
// }

$email = $_SESSION['email'] ?? 'example@uahb.sn';
// $id_structure = $_SESSION['id_structure'];
$statutUtilisateur = $_SESSION['statutUtilisateur'] ?? 1;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>UAHB - Environnement Numérique de Travail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta property="og:url" content="https://ent.uahb.sn" />
    <meta property="og:site_name" content="UAHB - ENT" />
    <link rel="canonical" href="https://ent.uahb.sn" />
    <link rel="shortcut icon" href="http://localhost/pedagogie/dist_assets/media/logos/1.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <link href="http://localhost/pedagogie/dist_assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
    <!-- <link href="http://localhost/pedagogie/dist_assets/css/style.css" rel="stylesheet" type="text/css" /> -->
    <link rel="stylesheet" href="maquetteCss">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.css">
    <?php
    $tacheId = $_POST['tacheId'] ?? null;
    $url = $_POST['url'] ?? null;
    $autreRessource = $_POST['autreRessource'] ?? null;
    ?>
    <script>
        const postData = {
            tacheId: <?php echo json_encode($tacheId); ?>,
            url: <?php echo json_encode($url); ?>,
            autreRessource: <?php echo json_encode($autreRessource); ?>
        };
        console.log(postData)
    </script>

</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed aside-enabled aside-fixed" style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
    <div class="d-flex flex-column flex-root">
        <div class="page d-flex flex-row flex-column-fluid">
            <div id="kt_aside" class="aside aside-light aside-hoverable" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
                <div class="aside-logo flex-column-auto " id="kt_aside_logo">
                    <a href="#">
                        <img alt="Logo" src="http://localhost/pedagogie/dist_assets/media/logos/1.png" class="h-50px logo " style="margin-left: 70px!important; margin-top: 5px;" />
                    </a>
                    <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
                        <span class="svg-icon svg-icon-1 rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path opacity="0.5" d="M14.2657 11.4343L18.45 7.25C18.8642 6.83579 18.8642 6.16421 18.45 5.75C18.0358 5.33579 17.3642 5.33579 16.95 5.75L11.4071 11.2929C11.0166 11.6834 11.0166 12.3166 11.4071 12.7071L16.95 18.25C17.3642 18.6642 18.0358 18.6642 18.45 18.25C18.8642 17.8358 18.8642 17.1642 18.45 16.75L14.2657 12.5657C13.9533 12.2533 13.9533 11.7467 14.2657 11.4343Z" fill="black" />
                                <path d="M8.2657 11.4343L12.45 7.25C12.8642 6.83579 12.8642 6.16421 12.45 5.75C12.0358 5.33579 11.3642 5.33579 10.95 5.75L5.40712 11.2929C5.01659 11.6834 5.01659 12.3166 5.40712 12.7071L10.95 18.25C11.3642 18.6642 12.0358 18.6642 12.45 18.25C12.8642 17.8358 12.8642 17.1642 12.45 16.75L8.2657 12.5657C7.95328 12.2533 7.95328 11.7467 8.2657 11.4343Z" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="aside-menu flex-column-fluid">
                    <?php
                    if ($statutUtilisateur !== 1) {
                        $current_user = json_encode($_SESSION['current_user'][0]['statutPoste']);
                    } else {
                        $current_user = 0;
                    }
                    ?>

                    <div class="hover-scroll-overlay-y my-5 my-lg-5" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
                        <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500" id="#kt_aside_menu" data-kt-menu="true" id="menu">
                            <div class="menu-item">
                                <div class="menu-content pb-2">
                                    <span class="menu-section text-muted text-uppercase fs-8 ls-1">Dashboard</span>
                                    <div id="user"></div>
                                </div>
                            </div>

                            <div class="menu-item nav" id="menu">
                                <?php if ($current_user == 1) { ?>
                                    <div class="">
                                        <div class="menu-link " type="button" role="tab">
                                            <span class="menu-icon">
                                                <span class="svg-icon svg-icon-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <rect x="2" y="2" width="9" height="9" rx="2" fill="black" />
                                                        <rect opacity="0.3" x="13" y="2" width="9" height="9" rx="2" fill="black" />
                                                        <rect opacity="0.3" x="13" y="13" width="9" height="9" rx="2" fill="black" />
                                                        <rect opacity="0.3" x="2" y="13" width="9" height="9" rx="2" fill="black" />
                                                    </svg>
                                                </span>
                                            </span>

                                            <a class="menu-title" href="http://localhost/pedagogie/personnel/gestionTache.php">Gestion des taches</a>

                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="menu-link " type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M21.7 18.9L18.6 15.8C17.9 16.9 16.9 17.9 15.8 18.6L18.9 21.7C19.3 22.1 19.9 22.1 20.3 21.7L21.7 20.3C22.1 19.9 22.1 19.3 21.7 18.9Z" fill="black" />
                                                <path opacity="0.3" d="M11 20C6 20 2 16 2 11C2 6 6 2 11 2C16 2 20 6 20 11C20 16 16 20 11 20ZM11 4C7.1 4 4 7.1 4 11C4 14.9 7.1 18 11 18C14.9 18 18 14.9 18 11C18 7.1 14.9 4 11 4ZM8 11C8 9.3 9.3 8 11 8C11.6 8 12 7.6 12 7C12 6.4 11.6 6 11 6C8.2 6 6 8.2 6 11C6 11.6 6.4 12 7 12C7.6 12 8 11.6 8 11Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/searchEtudiant/searchEtudiant.php">Recherche Etudiant</a>
                                </div>
                                <div class="menu-link active" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/EC_Note/viewECNote.php">Saisie et consultation</a>
                                </div>
                                <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/EC_Note/calculeMoyEc.php">Calcul Moyenne EC</a>
                                </div>
                                <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/EC_Note/calculMoyUE.php">Calcul Moyenne UE</a>
                                </div>
                                <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/EC_Note/consolidationSemestrielle.php">Consolidation Semestrielle</a>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                <div id="kt_header" class="header align-items-stretch">
                    <div class="container-fluid d-flex align-items-stretch justify-content-between">
                        <div class="d-flex align-items-center d-lg-none ms-n3 me-1" title="Show aside menu">
                            <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px" id="kt_aside_mobile_toggle">
                                <span class="svg-icon svg-icon-2x mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
                            <a href="#" class="d-lg-none">
                                <img alt="Logo" src="http://localhost/pedagogie/dist_assets/media/logos/1.png" class="h-30px" />
                            </a>
                        </div>
                        <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
                            <div class="d-flex align-items-stretch" id="kt_header_nav">
                                <div class="header-menu align-items-stretch" data-kt-drawer="true" data-kt-drawer-name="header-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_header_menu_mobile_toggle" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_body', lg: '#kt_header_nav'}">
                                    <div class="menu menu-lg-rounded menu-column menu-lg-row menu-state-bg menu-title-gray-700 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-400 fw-bold my-5 my-lg-0 align-items-stretch" id="#kt_header_menu" data-kt-menu="true">
                                        <div class="menu-item me-lg-1">
                                            <a class="menu-link py-3" href="#">
                                                <span class="menu-title">Environnement Numérique de Travail</span>

                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-stretch flex-shrink-0">
                                <div class="d-flex align-items-stretch flex-shrink-0">
                                    <div class="d-flex align-items-center ms-1 ms-lg-3" id="kt_header_user_menu_toggle">
                                        <div class="cursor-pointer symbol symbol-30px symbol-md-40px" data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                                            <img src="" id="photo1" />
                                        </div>
                                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px" data-kt-menu="true">
                                            <div class="menu-item px-3">
                                                <div class="menu-content d-flex align-items-center px-3">
                                                    <div class="symbol symbol-50px me-5">
                                                        <img src="" id="photo" />
                                                    </div>
                                                    <div class="d-flex flex-column">

                                                        <div class="fw-bolder d-flex align-items-center fs-5">
                                                            <!--<span id="prenom"></span>-->
                                                            <span id="nomAgent"></span>
                                                        </div>
                                                        <a href="#" class="fw-bold text-muted text-hover-primary fs-7"><?php echo htmlspecialchars($email); ?></a>
                                                        <a href="#" class="fw-bold text-muted text-hover-primary fs-7"><?php echo ($statutUtilisateur); ?></a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="separator my-2"></div>
                                            <div class="menu-item px-5">
                                                <!-- <a href="javascript:void(0)" class="menu-link px-5">Mon Profil</a> -->
                                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#edit_utilisateur">modifier mot de pass</button>
                                            </div>
                                            <!-- <div class="modal fade" id="edit_utilisateur" tabindex="-1" aria-labelledby="utilisateur" aria-hidden="true"> -->

                                            <!-- </div> -->
                                            <div class="separator my-2"></div>
                                            <div class="menu-item px-5">
                                                <div class="menu-content px-5">
                                                    <label class="form-check form-switch form-check-custom form-check-solid pulse pulse-success" for="kt_user_menu_dark_mode_toggle">
                                                        <!-- <a href="deconnexion.php" class="btn btn-success "><i class="bi bi-box-arrow-left fs-1"></i>Logout</a> -->
                                                        <input class="form-check-input w-30px h-20px"
                                                            checked="checked" type="checkbox" value="1" name="mode"
                                                            id="kt_user_menu_dark_mode_toggle"
                                                            data-kt-url="http://localhost/pedagogie/deconnexion.php" />
                                                        <span class="pulse-ring ms-n1"></span>
                                                        <span class="form-check-label text-gray-600 fs-7">se
                                                            déconnecter</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center d-lg-none ms-2 me-n3" title="Show header menu">
                                        <div class="btn btn-icon btn-active-light-primary w-30px h-30px w-md-40px h-md-40px" id="kt_header_menu_mobile_toggle">
                                            <span class="svg-icon svg-icon-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                    <path d="M13 11H3C2.4 11 2 10.6 2 10V9C2 8.4 2.4 8 3 8H13C13.6 8 14 8.4 14 9V10C14 10.6 13.6 11 13 11ZM22 5V4C22 3.4 21.6 3 21 3H3C2.4 3 2 3.4 2 4V5C2 5.6 2.4 6 3 6H21C21.6 6 22 5.6 22 5Z" fill="black" />
                                                    <path opacity="0.3" d="M21 16H3C2.4 16 2 15.6 2 15V14C2 13.4 2.4 13 3 13H21C21.6 13 22 13.4 22 14V15C22 15.6 21.6 16 21 16ZM14 20V19C14 18.4 13.6 18 13 18H3C2.4 18 2 18.4 2 19V20C2 20.6 2.4 21 3 21H13C13.6 21 14 20.6 14 20Z" fill="black" />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="post d-flex flex-column-fluid" id="kt_post">
                        <div id="kt_content_container" class="container-xxl">
                            <div class="tab-pane w-100" id="nav-tachePost" role="tabpanel" aria-labelledby="nav-tachePost-tab">

                                <div class="mt-1 container-fluid card shadow-sm">
                                    <div class="card-header border-0 pt-6">
                                        <div class="card-title d-flex flex-column">
                                            <h2 class="fw-bold">Gestion des Notes de EC : <?php echo $_GET['nomEc']; ?> <?php if (isset($_GET['typeNote'])) echo $_GET['typeNote']; ?></h2>
                                            <div class="d-flex align-items-center fs-6 fw-semibold text-muted">
                                                Moyenne de classe actuelle : <span class="badge badge-light-success ms-2" id="classeMoyenne">--/20</span>
                                            </div>
                                        </div>
                                        <div class="card-toolbar">
                                            <button id="toggleEdit" class="btn btn-warning me-2">
                                                <i class="bi bi-pencil-square"></i> Activer la Saisie
                                            </button>
                                            <button id="saveData" class="btn btn-success">
                                                <i class="bi bi-save"></i> Enregistrer
                                            </button>
                                        </div>
                                    </div>

                                    <div class="card-body py-4">
                                        <div class="table-responsive">
                                            
                                            <table id="tableUEEtudiant" class="table table-row-bordered table-hover gy-4 align-middle">
                                                <thead class="sticky-top bg-white shadow-sm">
                                                    <tr class="fw-bold fs-6 text-muted text-uppercase">
                                                        <th>Matricule</th>
                                                        <th>Nom complet</th>
                                                        <th>Anonymat</th>
                                                        <th style="width: 120px;">Note</th>
                                                        <th>Absence</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="mainTableBody">
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
    // 1. Déclarations des éléments DOM
    const tableBody = document.getElementById('mainTableBody');
    const toggleBtn = document.getElementById('toggleEdit');
    const saveBtn = document.getElementById('saveData');
    const modeLabel = document.getElementById('modeLabel');

    // 2. Données fictives
    const etudiants = ["Mamadou Diallo", "Fatou Sow", "Ousmane Ba", "Aïssatou Ndiaye", "Ibrahima Gueye", "Mariama Fall", "Cheikh Kane", "Khady Diop", "Abdoulaye Wade", "Aminata Toure"];
    const matieres = ["Algorithmique", "Réseaux", "Droit Civil", "Pathologie", "POO", "Base de Données", "Anglais"];

    // 3. Fonction de génération des lignes
    function generateTableRows(count) {
        let html = "";
        for (let i = 1; i <= count; i++) {
            const nom = etudiants[i % etudiants.length];
            const matiere = matieres[i % matieres.length];
            const matricule = `MAT${String(i).padStart(3, '0')}`;
            const anonymat = `ANON${String(i).padStart(3, '0')}`;
            const noteAleatoire = (Math.random() * 20).toFixed(1);

            html += `
                <tr class="student-row">
                    <td class="fw-bold text-gray-800">${matricule}</td>
                    <td>${nom}</td>
                    <td><span class="badge badge-light-primary">${anonymat}</span></td>
                    <td>
                        <input type="number" class="form-control form-control-sm note-input editable-field" 
                            value="${noteAleatoire}" min="0" max="20" step="0.5" disabled>
                    </td>
                    <td class="min-w-150px">
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check editable-field" name="absence-${i}" id="p-${i}" value="present" checked disabled>
                            <label class="btn btn-outline-success btn-sm fw-bold" for="p-${i}">P</label>

                            <input type="radio" class="btn-check editable-field" name="absence-${i}" id="aj-${i}" value="abs_j" disabled>
                            <label class="btn btn-outline-warning btn-sm fw-bold" for="aj-${i}">AJ</label>

                            <input type="radio" class="btn-check editable-field" name="absence-${i}" id="anj-${i}" value="abs_nj" disabled>
                            <label class="btn btn-outline-danger btn-sm fw-bold" for="anj-${i}">ANJ</label>
                        </div>
                    </td>
                </tr>`;
        }
        return html;
    }

    // 4. Fonction de mise à jour de l'UI (Mode Saisie vs Consultation)
    function setInterfaceMode(mode) {
        const isSaisie = (mode === 'saisie');
        const allFields = document.querySelectorAll('.editable-field');

        // Activer/Désactiver les champs
        allFields.forEach(field => field.disabled = !isSaisie);

        // Mettre à jour les moyennes si mode saisie
        if (isSaisie) {
            toggleBtn.innerHTML = '<i class="bi bi-eye"></i> Mode Consultation';
            toggleBtn.className = "btn btn-info active d-none";
            // saveBtn.classList.remove('d-none');
            
            modeLabel.textContent = "Saisie";
            modeLabel.className = "badge badge-light-warning";
        } else {
            toggleBtn.innerHTML = '<i class="bi bi-pencil-square"></i> Activer la Saisie';
            toggleBtn.className = "btn btn-warning";
            saveBtn.classList.add('d-none');
            modeLabel.textContent = "Consultation";
            modeLabel.className = "badge badge-light-primary";
        }
    }

    // --- INITIALISATION ---
    
    // Remplir le tableau

    tableBody.innerHTML = generateTableRows(30);

    // Définir le mode initial via PHP (ou par défaut)
    const initialAction = <?php echo json_encode($_GET['action'] ?? 'consultation'); ?>;
    setInterfaceMode(initialAction);

    // Gérer le clic sur le bouton de bascule
    toggleBtn.addEventListener('click', function() {
        const currentMode = modeLabel.textContent.trim().toLowerCase();
        const nextMode = (currentMode === 'saisie') ? 'consultation' : 'saisie';
        setInterfaceMode(nextMode);
    });
});
                                </script>
                                <!-- <script src="./script.js"></script> -->
                                <!-- <script src="../script.js"></script> -->
                            </div>
                            <div class="toolbar" id="kt_toolbar">
                                <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack">
                                    <div data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', 'lg': '#kt_toolbar_container'}" class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
                                        <h1 class="d-flex align-items-center text-dark fw-bolder fs-3 my-1" id="structure">
                                        </h1>
                                        <span class="h-20px border-gray-200 border-start mx-4"></span>
                                        <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">
                                            <a href="javascript:void(0)" class="text-dark text-hover-primary" id="service"></a>
                                            <span class="h-20px border-gray-200 border-start mx-4"></span>

                                            <li class="breadcrumb-item text-muted">
                                                <a href="javascript:void(0)" class="text-muted text-hover-primary">acceuil</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
        </div>
    </div>

    <!-- Core libs (jQuery first) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Global plugins and theme bundles -->
    <script src="http://localhost/pedagogie/dist_assets/plugins/global/plugins.bundle.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/scripts.bundle.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/script.js"></script>

    <!-- DataTables and other feature bundles -->
    <script src="http://localhost/pedagogie/dist_assets/plugins/custom/datatables/datatables.bundle.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/plugins/custom/fullcalendar/fullcalendar.bundle.js"></script>

    <!-- Optional / custom scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.0/dist/sweetalert2.min.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/custom/widgets.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/custom/apps/chat/chat.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/custom/modals/create-app.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/custom/modals/upgrade-plan.js"></script>
    <script src="http://localhost/pedagogie/dist_assets/js/jquery.validate.min.js"></script>

    <!-- Page script (after all libs) -->
    <!-- <script src="viewECNote.js"></script> -->

    <!-- Utilities and third-party libs used by page -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.3.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script>
        $('.date-own').datepicker({
            minViewMode: 2,
            format: 'yyyy'
        });
    </script>

    <!-- <script src="./maquette/script.js"></script> -->
</body>

</html>