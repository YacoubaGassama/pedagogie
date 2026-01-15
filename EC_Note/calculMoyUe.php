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
                                <div class="menu-link" type="button" role="tab">
                                    <span class="menu-icon">
                                        <span class="svg-icon svg-icon-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M16.0077 19.2901L12.9293 17.5311C12.3487 17.1993 11.6407 17.1796 11.0426 17.4787L6.89443 19.5528C5.56462 20.2177 4 19.2507 4 17.7639V5C4 3.89543 4.89543 3 6 3H17C18.1046 3 19 3.89543 19 5V17.5536C19 19.0893 17.341 20.052 16.0077 19.2901Z" fill="black" />
                                            </svg>
                                        </span>
                                    </span>

                                    <a class="menu-title" href="http://localhost/pedagogie/ueEtudiant/viewUEEtudiant.php">UE et Etudiants</a>
                                </div>
                                <div class="menu-link " type="button" role="tab">
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
                                <div class="menu-link active" type="button" role="tab">
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

                                <div id="ue-form-container" class="form-container" style="display: none;">
                                </div>

                                <div class="mt-1 container-fluid  p-5">
                                    <div class="border-0">
                                        <div class="card shadow-sm mb-4">
    <div class="card-body">
        <!-- Filtres améliorés -->
        <div id="filterContainer" class="mb-4 row g-3 align-items-end">
            <div class="col-md-3 col-lg-2">
                <label for="filterAnnee" class="form-label fw-semibold">Année Académique</label>
                <select id="filterAnnee" class="form-select form-select-sm">
                    <option value="">Toutes les années</option>
                    <option value="2025-2026">2025-2026</option>
                    <option value="2024-2025">2024-2025</option>
                    <option value="2023-2024">2023-2024</option>
                </select>
            </div>
            
            <div class="col-md-3 col-lg-2">
                <label for="filterSemestre" class="form-label fw-semibold">Période</label>
                <select id="filterSemestre" class="form-select form-select-sm">
                    <option value="">Tous les semestres</option>
                    <option value="1">Semestre 1</option>
                    <option value="2">Semestre 2</option>
                </select>
            </div>
            
            <div class="col-md-3 col-lg-2">
                <label for="filterSession" class="form-label fw-semibold">Session</label>
                <select id="filterSession" class="form-select form-select-sm">
                    <option value="">Toutes les sessions</option>
                    <option value="1">Normale</option>
                    <option value="2">Rattrapage</option>
                </select>
            </div>
            
            <div class="col-md-3 col-lg-2">
                <label for="filterCycle" class="form-label fw-semibold">Cycle</label>
                <select id="filterCycle" class="form-select form-select-sm">
                    <option value="">Tous les cycles</option>
                    <option value="Licence">Licence</option>
                    <option value="Master">Master</option>
                    <option value="Doctorat">Doctorat</option>
                </select>
            </div>
            
            <div class="col-md-3 col-lg-2">
                <label for="filterFiliere" class="form-label fw-semibold">Filière</label>
                <select id="filterFiliere" class="form-select form-select-sm" disabled>
                    <option value="">Toutes les filières</option>
                </select>
            </div>
            
            <div class="col-md-3 col-lg-2">
                <label for="niveau" class="form-label fw-semibold">Niveau</label>
                <select id="niveau" class="form-select form-select-sm" disabled>
                    <option value="">Tous les niveaux</option>
                </select>
            </div>
            
            <div class="col-md-3 col-lg-2">
                <label for="option" class="form-label fw-semibold">Option</label>
                <select id="option" class="form-select form-select-sm" disabled>
                    <option value="">Toutes les options</option>
                </select>
            </div>
            
            <div class="col-md-3 col-lg-2 d-flex align-items-center gap-2">
                <button id="resetFilters" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                </button>
                <button id="applyFilters" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Filtrer
                </button>
            </div>
        </div>

        <!-- Tableau avec les nouvelles colonnes -->
        <div class="table-responsive rounded border">
            <table id="tableUEEtudiant" class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th rowspan="2" class="align-middle border-end">Matricule</th>
                        <th rowspan="2" class="align-middle border-end">Nom Complet</th>
                        <th rowspan="2" class="align-middle border-end">Code Anonymat</th>
                        <th colspan="3" class="text-center border-end bg-info bg-opacity-10">UE 1: Mathématiques</th>
                        <th colspan="3" class="text-center border-end bg-warning bg-opacity-10">UE 2: Physique</th>
                        <th colspan="3" class="text-center border-end bg-success bg-opacity-10">UE 3: Informatique</th>
                        <th colspan="3" class="text-center bg-primary bg-opacity-10">UE 4: Chimie</th>
                    </tr>
                    <tr class="text-muted small">
                        <th class="text-center">Moy Gen</th>
                        <th class="text-center">Point Jury</th>
                        <th class="text-center border-end">Moy Retenue</th>
                        <th class="text-center">Moy Gen</th>
                        <th class="text-center">Point Jury</th>
                        <th class="text-center border-end">Moy Retenue</th>
                        <th class="text-center">Moy Gen</th>
                        <th class="text-center">Point Jury</th>
                        <th class="text-center border-end">Moy Retenue</th>
                        <th class="text-center">Moy Gen</th>
                        <th class="text-center">Point Jury</th>
                        <th class="text-center">Moy Retenue</th>
                    </tr>
                </thead>
                <tbody id="tableUEEtudiantBody" class="fw-semibold">
                    <!-- Les données seront insérées ici dynamiquement -->
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end fw-bold">Moyenne Générale:</td>
                        <td colspan="3" id="moyUe1" class="text-center fw-bold">-</td>
                        <td colspan="3" id="moyUe2" class="text-center fw-bold">-</td>
                        <td colspan="3" id="moyUe3" class="text-center fw-bold">-</td>
                        <td colspan="3" id="moyUe4" class="text-center fw-bold">-</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Indicateur de chargement -->
        <div id="loadingIndicator" class="text-center py-4 d-none">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="text-muted mt-2">Chargement des données...</p>
        </div>
        
        <!-- Message vide -->
        <div id="emptyMessage" class="text-center py-5 d-none">
            <i class="bi bi-table display-4 text-muted"></i>
            <p class="text-muted mt-3">Aucune donnée à afficher</p>
        </div>
    </div>
</div>

<!-- Modal pour les détails -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails des notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBodyContent">
                <!-- Contenu dynamique -->
            </div>
        </div>
    </div>
</div>

<style>
    /* Styles améliorés */
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
        cursor: pointer;
    }
    
    .table-active {
        background-color: rgba(0, 123, 255, 0.1) !important;
        border-left: 4px solid #0d6efd !important;
    }
    
    .note-success { color: #198754; }
    .note-danger { color: #dc3545; }
    .note-warning { color: #ffc107; }
    
    .badge-anon {
        background-color: #6c757d;
        color: white;
        font-family: monospace;
    }
    
    th {
        white-space: nowrap;
    }
    
    .point-jury {
        font-style: italic;
        color: #6c757d;
    }
    
    .moy-retenue {
        font-weight: bold;
        border: 2px solid transparent;
        padding: 2px 6px;
        border-radius: 4px;
    }
    
    .moy-retenue-success {
        color: #198754;
        background-color: rgba(25, 135, 84, 0.1);
        border-color: #198754;
    }
    
    .moy-retenue-danger {
        color: #dc3545;
        background-color: rgba(220, 53, 69, 0.1);
        border-color: #dc3545;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration
    const config = {
        niveauxParCycle: {
            "Licence": ["L1", "L2", "L3"],
            "Master": ["M1", "M2"],
            "Doctorat": ["D1", "D2", "D3"]
        },
        filieresParCycle: {
            "Licence": ["Mathématiques", "Physique", "Informatique"],
            "Master": ["Data Science", "Cybersécurité", "IA"],
            "Doctorat": ["Mathématiques Avancées", "Physique Quantique"]
        },
        optionsParFiliere: {
            "Informatique": ["Développement Web", "Réseaux", "Base de données"],
            "Mathématiques": ["Analyse", "Algèbre", "Statistiques"],
            "Physique": ["Mécanique", "Électromagnétisme", "Quantique"]
        }
    };
    
    // Éléments DOM
    const elements = {
        cycleSelect: document.getElementById('filterCycle'),
        niveauSelect: document.getElementById('niveau'),
        filiereSelect: document.getElementById('filterFiliere'),
        optionSelect: document.getElementById('option'),
        tableBody: document.getElementById('tableUEEtudiantBody'),
        loadingIndicator: document.getElementById('loadingIndicator'),
        emptyMessage: document.getElementById('emptyMessage'),
        resetBtn: document.getElementById('resetFilters'),
        applyBtn: document.getElementById('applyFilters'),
        moyenneElements: {
            ue1: document.getElementById('moyUe1'),
            ue2: document.getElementById('moyUe2'),
            ue3: document.getElementById('moyUe3'),
            ue4: document.getElementById('moyUe4')
        }
    };
    
    // Données
    let etudiants = [];
    let filteredEtudiants = [];

    // Classe Etudiant avec les nouvelles colonnes
    class Etudiant {
        constructor(mat, nom, anon, notes, cycle = "Licence", niveau = "L1", filiere = "Informatique", annee = "2025-2026") {
            this.matricule = mat;
            this.nomComplet = nom;
            this.codeAnon = anon;
            this.notes = notes; // Tableau d'objets: [{moyGen, pointJury, moyRetenue}, ...]
            this.cycle = cycle;
            this.niveau = niveau;
            this.filiere = filiere;
            this.annee = annee;
            this.semestre = "1";
            this.session = "1";
        }

        // Calculer la moyenne retenue avec la logique de point du jury
        calculerMoyenneRetenue(moyGen, pointJury) {
            let moyRetenue = moyGen;
            
            // Logique du point du jury:
            // - Si pointJury est positif, l'ajouter à la moyenne
            // - Si pointJury est négatif, le soustraire
            // - Si pointJury est 0, garder la moyenne d'origine
            if (pointJury > 0) {
                moyRetenue = Math.min(20, moyGen + pointJury); // Ne pas dépasser 20
            } else if (pointJury < 0) {
                moyRetenue = Math.max(0, moyGen + pointJury); // Ne pas descendre en dessous de 0
            }
            
            return Number(moyRetenue.toFixed(2));
        }

        getStatutUE(moyRetenue) {
            if (moyRetenue >= 10) return { texte: "Validé", classe: "text-success" };
            if (moyRetenue >= 8) return { texte: "Rattrapage", classe: "text-warning" };
            return { texte: "Échec", classe: "text-danger" };
        }

        getMoyenneGenerale() {
            const moyennesRetenues = this.notes.map(note => note.moyRetenue);
            const somme = moyennesRetenues.reduce((acc, val) => acc + val, 0);
            return Number((somme / moyennesRetenues.length).toFixed(2));
        }

        getStatutGeneral() {
            const moyenne = this.getMoyenneGenerale();
            // Vérifier si toutes les UE sont validées
            const toutesUEValidees = this.notes.every(note => note.moyRetenue >= 10);
            
            if (toutesUEValidees && moyenne >= 10) {
                return { texte: "Admis", classe: "text-success" };
            }
            if (moyenne >= 8) {
                return { texte: "Rattrapage", classe: "text-warning" };
            }
            return { texte: "Échec", classe: "text-danger" };
        }

        renderRow() {
            let htmlNotes = "";
            this.notes.forEach((note, index) => {
                const moyRetenue = this.calculerMoyenneRetenue(note.moyGen, note.pointJury);
                const statut = this.getStatutUE(moyRetenue);
                const pointJuryClass = note.pointJury > 0 ? "text-success" : note.pointJury < 0 ? "text-danger" : "text-muted";
                const moyRetenueClass = moyRetenue >= 10 ? "moy-retenue-success" : "moy-retenue-danger";
                
                htmlNotes += `
                    <td class="text-center fw-bold">${note.moyGen.toFixed(2)}</td>
                    <td class="text-center point-jury ${pointJuryClass}">${note.pointJury > 0 ? '+' : ''}${note.pointJury.toFixed(1)}</td>
                    <td class="text-center"><span class="moy-retenue ${moyRetenueClass}">${moyRetenue.toFixed(2)}</span></td>
                `;
            });

            const statutGeneral = this.getStatutGeneral();
            
            return `
                <tr data-id="${this.matricule}" onclick="selectionnerEtudiant('${this.matricule}')">
                    <td class="fw-bold">${this.matricule}</td>
                    <td>${this.nomComplet}</td>
                    <td><span class="badge badge-anon rounded-pill">${this.codeAnon}</span></td>
                    ${htmlNotes}
                </tr>`;
        }
        
        renderDetails() {
            let details = `
                <h6>${this.nomComplet} <small class="text-muted">(${this.matricule})</small></h6>
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Cycle:</strong> ${this.cycle}</div>
                    <div class="col-md-3"><strong>Niveau:</strong> ${this.niveau}</div>
                    <div class="col-md-3"><strong>Filière:</strong> ${this.filiere}</div>
                    <div class="col-md-3"><strong>Année:</strong> ${this.annee}</div>
                </div>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>UE</th>
                            <th class="text-center">Moyenne Générale</th>
                            <th class="text-center">Point du Jury</th>
                            <th class="text-center">Moyenne Retenue</th>
                            <th class="text-center">Statut UE</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            this.notes.forEach((note, index) => {
                const moyRetenue = this.calculerMoyenneRetenue(note.moyGen, note.pointJury);
                const statut = this.getStatutUE(moyRetenue);
                const pointJuryDisplay = note.pointJury > 0 ? `+${note.pointJury.toFixed(1)}` : note.pointJury.toFixed(1);
                
                details += `
                    <tr>
                        <td>UE ${index + 1}</td>
                        <td class="text-center fw-bold">${note.moyGen.toFixed(2)}</td>
                        <td class="text-center ${note.pointJury > 0 ? 'text-success' : note.pointJury < 0 ? 'text-danger' : 'text-muted'}">${pointJuryDisplay}</td>
                        <td class="text-center fw-bold ${statut.classe}">${moyRetenue.toFixed(2)}</td>
                        <td class="text-center"><span class="badge ${moyRetenue >= 10 ? 'bg-success' : moyRetenue >= 8 ? 'bg-warning' : 'bg-danger'}">${statut.texte}</span></td>
                    </tr>`;
            });
            
            const moyenneGenerale = this.getMoyenneGenerale();
            const statutGeneral = this.getStatutGeneral();
            
            details += `
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Moyenne Générale:</td>
                            <td class="text-center fw-bold ${statutGeneral.classe}">${moyenneGenerale.toFixed(2)}</td>
                            <td class="text-center"><span class="badge ${moyenneGenerale >= 10 ? 'bg-success' : moyenneGenerale >= 8 ? 'bg-warning' : 'bg-danger'}">${statutGeneral.texte}</span></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="mt-3 p-3 bg-light rounded">
                    <h6 class="fw-bold">Légende:</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <span class="text-success me-1">■</span> Point du jury positif
                        </div>
                        <div class="col-md-4">
                            <span class="text-danger me-1">■</span> Point du jury négatif
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted me-1">■</span> Point du jury neutre
                        </div>
                    </div>
                </div>`;
            
            return details;
        }
    }

    // Initialisation des données avec les nouvelles colonnes
    function initialiserDonnees() {
        etudiants = [
            new Etudiant("MAT001", "Diop Ali", "ANON-101", [
                { moyGen: 13.75, pointJury: 0.5, moyRetenue: 14.25 },
                { moyGen: 14.25, pointJury: 1.0, moyRetenue: 15.25 },
                { moyGen: 12.50, pointJury: -0.5, moyRetenue: 12.00 },
                { moyGen: 15.50, pointJury: 0.0, moyRetenue: 15.50 }
            ], "Licence", "L1", "Informatique"),
            
            new Etudiant("MAT002", "Sow Marie", "ANON-102", [
                { moyGen: 11.50, pointJury: 1.5, moyRetenue: 13.00 },
                { moyGen: 12.75, pointJury: 0.0, moyRetenue: 12.75 },
                { moyGen: 13.50, pointJury: -1.0, moyRetenue: 12.50 },
                { moyGen: 10.75, pointJury: 0.5, moyRetenue: 11.25 }
            ], "Licence", "L2", "Mathématiques"),
            
            new Etudiant("MAT003", "Ba Fatou", "ANON-103", [
                { moyGen: 14.50, pointJury: 0.0, moyRetenue: 14.50 },
                { moyGen: 15.00, pointJury: 0.5, moyRetenue: 15.50 },
                { moyGen: 11.75, pointJury: 2.0, moyRetenue: 13.75 },
                { moyGen: 13.25, pointJury: 0.0, moyRetenue: 13.25 }
            ], "Master", "M1", "Data Science"),
            
            new Etudiant("MAT004", "Ndiaye Jean", "ANON-104", [
                { moyGen: 9.75, pointJury: 0.0, moyRetenue: 9.75 },
                { moyGen: 10.50, pointJury: 1.0, moyRetenue: 11.50 },
                { moyGen: 12.50, pointJury: -0.5, moyRetenue: 12.00 },
                { moyGen: 14.00, pointJury: 0.5, moyRetenue: 14.50 }
            ], "Licence", "L3", "Physique"),
            
            new Etudiant("MAT005", "Fall Oumar", "ANON-105", [
                { moyGen: 7.50, pointJury: 2.0, moyRetenue: 9.50 },
                { moyGen: 10.00, pointJury: 0.0, moyRetenue: 10.00 },
                { moyGen: 9.50, pointJury: 1.5, moyRetenue: 11.00 },
                { moyGen: 11.00, pointJury: -1.0, moyRetenue: 10.00 }
            ], "Doctorat", "D1", "Mathématiques Avancées")
        ];
        
        // Calculer les moyennes retenues pour chaque étudiant
        etudiants.forEach(etudiant => {
            etudiant.notes.forEach(note => {
                note.moyRetenue = etudiant.calculerMoyenneRetenue(note.moyGen, note.pointJury);
            });
        });
        
        filteredEtudiants = [...etudiants];
        afficherEtudiants();
        calculerMoyennesGenerales();
    }

    // Gestion des filtres (inchangée)
    function initialiserFiltres() {
        elements.cycleSelect.addEventListener('change', function() {
            const cycle = this.value;
            
            elements.niveauSelect.innerHTML = '<option value="">Tous les niveaux</option>';
            elements.filiereSelect.innerHTML = '<option value="">Toutes les filières</option>';
            elements.optionSelect.innerHTML = '<option value="">Toutes les options</option>';
            
            elements.niveauSelect.disabled = !cycle;
            elements.filiereSelect.disabled = !cycle;
            elements.optionSelect.disabled = true;
            
            if (cycle) {
                if (cycle === "") {
                    const tousNiveaux = Object.values(config.niveauxParCycle).flat();
                    tousNiveaux.forEach(niv => {
                        elements.niveauSelect.innerHTML += `<option value="${niv}">${niv}</option>`;
                    });
                } else {
                    config.niveauxParCycle[cycle].forEach(niv => {
                        elements.niveauSelect.innerHTML += `<option value="${niv}">${niv}</option>`;
                    });
                }
                
                if (cycle === "") {
                    const toutesFilieres = Object.values(config.filieresParCycle).flat();
                    const filieresUniques = [...new Set(toutesFilieres)];
                    filieresUniques.forEach(fil => {
                        elements.filiereSelect.innerHTML += `<option value="${fil}">${fil}</option>`;
                    });
                } else {
                    config.filieresParCycle[cycle]?.forEach(fil => {
                        elements.filiereSelect.innerHTML += `<option value="${fil}">${fil}</option>`;
                    });
                }
            }
        });
        
        elements.filiereSelect.addEventListener('change', function() {
            const filiere = this.value;
            elements.optionSelect.innerHTML = '<option value="">Toutes les options</option>';
            elements.optionSelect.disabled = !filiere;
            
            if (filiere && config.optionsParFiliere[filiere]) {
                config.optionsParFiliere[filiere].forEach(opt => {
                    elements.optionSelect.innerHTML += `<option value="${opt}">${opt}</option>`;
                });
            }
        });
        
        elements.resetBtn.addEventListener('click', function() {
            document.querySelectorAll('#filterContainer select').forEach(select => {
                select.value = "";
                if (select.id !== 'filterCycle') {
                    select.disabled = true;
                }
            });
            filteredEtudiants = [...etudiants];
            afficherEtudiants();
            calculerMoyennesGenerales();
        });
        
        elements.applyBtn.addEventListener('click', appliquerFiltres);
        
        document.querySelectorAll('#filterContainer select').forEach(select => {
            select.addEventListener('change', function() {
                if (this.id !== 'filterCycle' && this.id !== 'filterFiliere') {
                    appliquerFiltres();
                }
            });
        });
    }

    function appliquerFiltres() {
        elements.loadingIndicator.classList.remove('d-none');
        
        setTimeout(() => {
            const filters = {
                cycle: elements.cycleSelect.value,
                niveau: elements.niveauSelect.value,
                filiere: elements.filiereSelect.value,
                option: elements.optionSelect.value,
                annee: document.getElementById('filterAnnee').value,
                semestre: document.getElementById('filterSemestre').value,
                session: document.getElementById('filterSession').value
            };
            
            filteredEtudiants = etudiants.filter(etudiant => {
                return Object.entries(filters).every(([key, value]) => {
                    if (!value) return true;
                    return etudiant[key] === value;
                });
            });
            
            afficherEtudiants();
            calculerMoyennesGenerales();
            elements.loadingIndicator.classList.add('d-none');
        }, 300);
    }

    // Affichage des étudiants
    function afficherEtudiants() {
        if (filteredEtudiants.length === 0) {
            elements.tableBody.innerHTML = '';
            elements.emptyMessage.classList.remove('d-none');
            return;
        }
        
        elements.emptyMessage.classList.add('d-none');
        elements.tableBody.innerHTML = filteredEtudiants.map(etu => etu.renderRow()).join('');
    }

    // Calcul des moyennes générales par UE (moyenne des moyennes retenues)
    function calculerMoyennesGenerales() {
        if (filteredEtudiants.length === 0) {
            Object.values(elements.moyenneElements).forEach(el => el.textContent = '-');
            return;
        }
        
        const sommesUEs = Array(4).fill(0);
        const compteursUEs = Array(4).fill(0);
        
        filteredEtudiants.forEach(etudiant => {
            etudiant.notes.forEach((note, index) => {
                if (note) {
                    sommesUEs[index] += note.moyRetenue;
                    compteursUEs[index]++;
                }
            });
        });
        
        sommesUEs.forEach((somme, index) => {
            const moyenne = compteursUEs[index] > 0 ? (somme / compteursUEs[index]).toFixed(2) : '-';
            const element = elements.moyenneElements[`ue${index + 1}`];
            element.textContent = moyenne;
            
            if (moyenne !== '-') {
                element.className = `text-center fw-bold ${moyenne >= 10 ? 'text-success' : 'text-danger'}`;
            }
        });
    }

    // Sélection d'un étudiant
    window.selectionnerEtudiant = function(matricule) {
        document.querySelectorAll('#tableUEEtudiantBody tr').forEach(tr => {
            tr.classList.remove('table-active');
        });
        
        const ligne = document.querySelector(`tr[data-id="${matricule}"]`);
        if (ligne) {
            ligne.classList.add('table-active');
        }
        
        const etudiant = etudiants.find(e => e.matricule === matricule);
        if (etudiant) {
            document.getElementById('modalBodyContent').innerHTML = etudiant.renderDetails();
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        }
    };

    // Initialiser l'application
    initialiserDonnees();
    initialiserFiltres();
});
</script>
                                        <!-- modal saisie note etudiant -->
                                        <div class="modal fade" id="saisieNoteModal" tabindex="-1" aria-labelledby="saisieNoteModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="saisieNoteModalLabel">Saisie des Notes des Étudiants</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                                        <div class="table-responsive">
                                                            <div id="saisieNoteModalBody"></div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                        <button type="button" class="btn btn-primary" id="saveNotesButton">Enregistrer les Notes</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- modal cosultation note etudiants  -->
                                        <div class="modal fade" id="consultationNoteModal" tabindex="-1" aria-labelledby="consultationNoteModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="consultationNoteModalLabel">Consultation des Notes des Étudiants</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                                        <div class="table-responsive">
                                                            <div id="consultationNoteModalBody"></div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- etudiantsUEModal -->
                                        <div class="modal fade" id="etudiantsUEModal" tabindex="-1" aria-labelledby="etudiantsUEModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="etudiantsUEModalLabel">Étudiants inscrits à l'UE</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                                                        <div class="table-responsive">
                                                            <div id="etudiantsUEModalBody"></div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
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
</body>

</html>