<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Repositories\DashboardAnalyticsRepository;
use App\Repositories\HierarchyRepository;
use App\Repositories\ImportBatchRepository;
use App\Repositories\ImportQueueRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PostSaleLogRepository;
use App\Repositories\PostSaleRepository;
use App\Repositories\ReportsRepository;
use App\Repositories\SaleLogRepository;
use App\Repositories\SalesRepository;
use App\Repositories\UserRepository;
use App\Services\HierarchyExportService;
use App\Services\HierarchyImportService;
use App\Services\ImportService;
use App\Services\ProductCatalogExportService;
use App\Services\ProductCatalogImportService;
use App\Services\PostSaleImportService;
use App\Services\ReportsExportService;

$route = (string) ($_GET['route'] ?? (Auth::check() ? 'dashboard' : 'login'));

if ($route === 'login') {
    if (Auth::check()) {
        redirect(Auth::mustChangePassword() ? 'force-password' : 'dashboard');
    }

    $loginViewData = static fn(string $cpf = ''): array => [
        'title' => 'Entrar',
        'cpf' => $cpf,
        'authInlineFlashes' => true,
    ];

    if (is_post()) {
        if (! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            render('auth/login', $loginViewData(clean_document($_POST['cpf'] ?? '')), 'auth');
            return;
        }

        $cpf = clean_document($_POST['cpf'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($cpf === '' || $password === '') {
            flash('error', 'Informe CPF e senha para entrar.');
            render('auth/login', $loginViewData($cpf), 'auth');
            return;
        }

        $loginUser = (new UserRepository())->findByCpf($cpf);

        if ($loginUser !== null && ! (bool) ($loginUser['is_active'] ?? 0)) {
            flash('error', 'Usuário inativo, contate um administrador!');
            render('auth/login', $loginViewData($cpf), 'auth');
            return;
        }

        if (! Auth::attempt($cpf, $password)) {
            flash('error', 'Credenciais inválidas.');
            render('auth/login', $loginViewData($cpf), 'auth');
            return;
        }

        if (Auth::mustChangePassword()) {
            redirect('force-password');
        }

        flash('success', 'Login realizado com sucesso.');
        redirect('dashboard');
    }

    render('auth/login', $loginViewData(clean_document($_GET['cpf'] ?? '')), 'auth');
    return;
}

if ($route === 'logout') {
    if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
        flash('error', 'Solicitação inválida.');
        redirect('dashboard');
    }

    Auth::logout();
    session_start();
    flash('success', 'Sessão encerrada.');
    redirect('login');
}

if ($route === 'force-password') {
    Auth::requireLogin();

    if (! Auth::mustChangePassword()) {
        redirect('dashboard');
    }

    $userRepository = new UserRepository();

    if (is_post()) {
        if (! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            render('auth/force-password', ['title' => 'Primeiro acesso'], 'auth');
            return;
        }

        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirmation = (string) ($_POST['new_password_confirmation'] ?? '');
        $passwordError = strong_password_error($newPassword, $newPasswordConfirmation);

        if ($passwordError !== null) {
            flash('error', $passwordError);
            render('auth/force-password', ['title' => 'Primeiro acesso'], 'auth');
            return;
        }

        try {
            $userRepository->changePassword((int) Auth::id(), $newPassword);
            $currentUserCpf = clean_document((string) (Auth::user()['cpf'] ?? ''));
            Auth::logout();
            session_start();
            flash('success', 'Senha alterada com sucesso. Entre novamente com a nova senha.');
            redirect('login', ['cpf' => $currentUserCpf]);
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            render('auth/force-password', ['title' => 'Primeiro acesso'], 'auth');
            return;
        }
    }

    render('auth/force-password', ['title' => 'Primeiro acesso'], 'auth');
    return;
}

Auth::requireLogin();

if (Auth::mustChangePassword()) {
    redirect('force-password');
}

$hierarchyRepository = new HierarchyRepository();

if (
    str_starts_with($route, 'hierarchy')
    || str_starts_with($route, 'users')
    || str_starts_with($route, 'products')
    || str_starts_with($route, 'reports')
    || str_starts_with($route, 'post-sales')
) {
    Auth::requireRole('ADMINISTRADOR');
}

$queueRepository = new ImportQueueRepository();
$batchRepository = new ImportBatchRepository();
$dashboardAnalyticsRepository = new DashboardAnalyticsRepository();
$productRepository = new ProductRepository();
$postSaleRepository = new PostSaleRepository();
$postSaleLogRepository = new PostSaleLogRepository();
$reportsRepository = new ReportsRepository();
$saleLogRepository = new SaleLogRepository();
$userRepository = new UserRepository();

switch ($route) {
    case 'dashboard':
        render('dashboard/index', dashboardViewData($queueRepository, $batchRepository, $dashboardAnalyticsRepository) + [
            'title' => 'Dashboard',
        ]);
        break;

    case 'dashboard/live':
        if (! Auth::hasRole('ADMINISTRADOR', 'BACKOFFICE SUPERVISOR')) {
            http_response_code(403);
            exit;
        }

        if ((int) ($_GET['executive'] ?? 0) !== 1) {
            http_response_code(204);
            exit;
        }

        echo view('dashboard/_live', dashboardViewData($queueRepository, $batchRepository, $dashboardAnalyticsRepository));
        exit;

    case 'import':
        if (is_post()) {
            if (! Csrf::verify($_POST['_token'] ?? null)) {
                flash('error', 'Token de segurança inválido.');
                redirect('import');
            }

            try {
                $service = new ImportService();
                $result = $service->importFromZip($_FILES['zip_file'] ?? [], (int) Auth::id());
                flash(
                    'success',
                    sprintf(
                        'Importação concluída. Total lido: %d | Elegíveis: %d | Novas: %d | Duplicadas: %d | Filtradas: %d',
                        $result['total_rows'],
                        $result['eligible_rows'],
                        $result['imported_count'],
                        $result['duplicate_count'],
                        $result['filtered_out_count']
                    )
                );
            } catch (Throwable $throwable) {
                flash('error', $throwable->getMessage());
            }

            redirect('import');
        }

        render('import/index', [
            'title' => 'Importação PAP MOBILE',
            'recentBatches' => $batchRepository->recent(10),
        ]);
        break;

    case 'hierarchy':
        $hierarchyTermFilter = normalize_text($_GET['term'] ?? '');
        $hierarchyBaseFilter = normalize_positive_ids($_GET['base_id'] ?? []);
        $hierarchyRoleFilter = normalize_allowed_upper_values($_GET['role'] ?? [], $hierarchyRepository->roles());
        $hierarchyPage = max(1, (int) ($_GET['page'] ?? 1));
        $editingBaseId = (int) ($_GET['edit_base'] ?? 0);
        $editingGroupId = (int) ($_GET['edit_group'] ?? 0);
        $newGroupBaseId = (int) ($_GET['new_group_base'] ?? 0);
        $latestHeadcountPeriod = $hierarchyRepository->latestHeadcountPeriod();
        $baseFormData = hierarchyBaseFormData($hierarchyRepository->findBase($editingBaseId));
        $groupFormData = hierarchyGroupFormData($hierarchyRepository->findGroup($editingGroupId));
        $manageBasesSelectedBaseId = max(0, (int) ($_GET['open_base'] ?? 0));

        if ($manageBasesSelectedBaseId <= 0 && $newGroupBaseId > 0) {
            $manageBasesSelectedBaseId = $newGroupBaseId;
        }

        if ($manageBasesSelectedBaseId <= 0 && (int) ($groupFormData['base_id'] ?? 0) > 0) {
            $manageBasesSelectedBaseId = (int) $groupFormData['base_id'];
        }

        $hierarchySellerResult = $hierarchyRepository->sellers(
            $hierarchyTermFilter !== '' ? $hierarchyTermFilter : null,
            $hierarchyBaseFilter,
            $hierarchyRoleFilter,
            $hierarchyPage,
            50,
            $latestHeadcountPeriod
        );
        $hierarchyGroups = $hierarchyRepository->groups();
        render('hierarchy/index', [
            'title' => 'Hierarquia',
            'stats' => $hierarchyRepository->stats(),
            'bases' => $hierarchyRepository->bases(),
            'groups' => $hierarchyGroups,
            'groupsByBase' => $hierarchyRepository->groupOptionsByBase(),
            'hierarchyRoles' => $hierarchyRepository->roles(),
            'sellers' => $hierarchySellerResult['items'],
            'sellerCurrentPage' => $hierarchySellerResult['page'],
            'sellerPerPage' => $hierarchySellerResult['per_page'],
            'sellerTotal' => $hierarchySellerResult['total'],
            'sellerTotalPages' => $hierarchySellerResult['total_pages'],
            'sellerTermFilter' => $hierarchyTermFilter,
            'sellerBaseFilter' => $hierarchyBaseFilter,
            'sellerRoleFilter' => $hierarchyRoleFilter,
            'latestHeadcountPeriod' => $latestHeadcountPeriod,
            'headcountPeriods' => $hierarchyRepository->headcountPeriods(),
            'activeHeadcountEdit' => $hierarchyRepository->activeHeadcountEdit(),
            'baseForm' => $baseFormData,
            'groupForm' => $groupFormData,
            'sellerForm' => hierarchySellerFormData($hierarchyRepository->findSeller((int) ($_GET['edit_seller'] ?? 0))),
            'manageBasesOpen' => ((int) ($_GET['manage_bases'] ?? 0) === 1) || $editingBaseId > 0 || $editingGroupId > 0 || $newGroupBaseId > 0,
            'manageBasesSelectedBaseId' => $manageBasesSelectedBaseId,
            'manageBasesNewGroupBaseId' => $newGroupBaseId,
            'headcountImportOpen' => (int) ($_GET['import_headcount'] ?? 0) === 1,
            'headcountExportOpen' => (int) ($_GET['export_headcount'] ?? 0) === 1,
        ]);
        break;

    case 'hierarchy/export':
        try {
            $hierarchyTermFilter = normalize_text($_GET['term'] ?? '');
            $hierarchyBaseFilter = normalize_positive_ids($_GET['base_id'] ?? []);
            $hierarchyRoleFilter = normalize_allowed_upper_values($_GET['role'] ?? [], $hierarchyRepository->roles());
            $headcountPeriod = clean_document($_GET['period_headcount'] ?? '');
            $rows = $hierarchyRepository->exportRows(
                $hierarchyTermFilter !== '' ? $hierarchyTermFilter : null,
                $hierarchyBaseFilter,
                $hierarchyRoleFilter,
                preg_match('/^\d{6}$/', $headcountPeriod) ? $headcountPeriod : null
            );

            if ($rows === []) {
                flash('error', 'Nenhuma hierarquia encontrada para exportar.');
                redirect('hierarchy', [
                    'term' => $hierarchyTermFilter !== '' ? $hierarchyTermFilter : null,
                    'base_id' => $hierarchyBaseFilter !== [] ? $hierarchyBaseFilter : null,
                    'role' => $hierarchyRoleFilter !== [] ? $hierarchyRoleFilter : null,
                    'export_headcount' => 1,
                ]);
            }

            if (preg_match('/^\d{6}$/', $headcountPeriod)) {
                $hierarchyRepository->registerHeadcountDownload((int) Auth::id(), $headcountPeriod);
            }

            (new HierarchyExportService())->download($rows);
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            redirect('hierarchy');
        }
        break;

    case 'hierarchy/import':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('hierarchy', ['import_headcount' => 1]);
        }

        $uploadedFile = $_FILES['hierarchy_file'] ?? null;

        if (! is_array($uploadedFile) || (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Selecione um arquivo XLSX válido para importar.');
            redirect('hierarchy', ['import_headcount' => 1]);
        }

        $originalName = (string) ($uploadedFile['name'] ?? '');
        $tmpFile = (string) ($uploadedFile['tmp_name'] ?? '');

        if ($tmpFile === '' || ! is_uploaded_file($tmpFile)) {
            flash('error', 'Não foi possível receber o arquivo enviado.');
            redirect('hierarchy', ['import_headcount' => 1]);
        }

        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            flash('error', 'Envie uma planilha no formato XLSX.');
            redirect('hierarchy', ['import_headcount' => 1]);
        }

        try {
            $stats = (new HierarchyImportService())->importFromXlsx($tmpFile, (int) Auth::id());
            $hierarchyRepository->resolveHeadcountEditAfterImport((int) Auth::id());
            flash(
                'success',
                sprintf(
                    'Headcount importado. Processadas: %d | Inseridas: %d | Atualizadas: %d',
                    (int) ($stats['processed'] ?? 0),
                    (int) ($stats['inserted'] ?? 0),
                    (int) ($stats['updated'] ?? 0)
                )
            );
            redirect('hierarchy');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            redirect('hierarchy', ['import_headcount' => 1]);
        }
        break;

    case 'hierarchy/base/save':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('hierarchy', ['manage_bases' => 1]);
        }

        $baseId = normalize_positive_id($_POST['id'] ?? null);

        try {
            $savedBaseId = $hierarchyRepository->saveBase($baseId, (string) ($_POST['name'] ?? ''), (string) ($_POST['regional'] ?? ''));
            flash('success', $baseId ? 'Operação/base atualizada com sucesso.' : 'Operação/base cadastrada com sucesso.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            redirect('hierarchy', [
                'manage_bases' => 1,
                'edit_base' => $baseId,
                'open_base' => $baseId,
            ]);
        }

        redirect('hierarchy', [
            'manage_bases' => 1,
            'open_base' => $savedBaseId ?? $baseId,
        ]);
        break;

    case 'hierarchy/group/save':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('hierarchy', ['manage_bases' => 1]);
        }

        $groupId = normalize_positive_id($_POST['id'] ?? null);
        $groupBaseId = (int) ($_POST['base_id'] ?? 0);

        try {
            $hierarchyRepository->saveGroup($groupId, $groupBaseId, (string) ($_POST['name'] ?? ''));
            flash('success', $groupId ? 'Base grupo atualizado com sucesso.' : 'Base grupo cadastrado com sucesso.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            redirect('hierarchy', [
                'manage_bases' => 1,
                'open_base' => $groupBaseId > 0 ? $groupBaseId : null,
                'new_group_base' => $groupBaseId > 0 && $groupId === null ? $groupBaseId : null,
                'edit_group' => $groupId,
            ]);
        }

        redirect('hierarchy', [
            'manage_bases' => 1,
            'open_base' => $groupBaseId > 0 ? $groupBaseId : null,
        ]);
        break;

    case 'hierarchy/base/delete':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('hierarchy', ['manage_bases' => 1]);
        }

        try {
            $baseId = (int) ($_GET['id'] ?? 0);
            $hierarchyRepository->deleteBase($baseId);
            flash('success', 'Operação/base excluída com sucesso.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
        }

        redirect('hierarchy', ['manage_bases' => 1]);
        break;

    case 'hierarchy/group/delete':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('hierarchy', ['manage_bases' => 1]);
        }

        $groupId = (int) ($_GET['id'] ?? 0);
        $groupRecord = $hierarchyRepository->findGroup($groupId);

        try {
            $hierarchyRepository->deleteGroup($groupId);
            flash('success', 'Base grupo excluído com sucesso.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
        }

        redirect('hierarchy', [
            'manage_bases' => 1,
            'open_base' => (int) ($groupRecord['base_id'] ?? 0) > 0 ? (int) $groupRecord['base_id'] : null,
        ]);
        break;

    case 'hierarchy/seller/save':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('hierarchy');
        }

        try {
            $sellerId = normalize_positive_id($_POST['id'] ?? null);
            $hierarchyRepository->saveSeller($sellerId, $_POST, (int) Auth::id());
            flash('success', $sellerId ? 'Hierarquia do vendedor atualizada com sucesso.' : 'Hierarquia do vendedor cadastrada com sucesso.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
        }

        redirect('hierarchy');
        break;

    case 'users':
        $userTermFilter = normalize_text($_GET['term'] ?? '');
        $userRoleFilter = strtoupper(normalize_text($_GET['role'] ?? ''));
        $userRegionalViewFilter = strtoupper(normalize_text($_GET['regional_view'] ?? ''));
        render('users/index', [
            'title' => 'Usuários',
            'stats' => $userRepository->stats(),
            'users' => $userRepository->all(
                $userTermFilter !== '' ? $userTermFilter : null,
                $userRoleFilter !== '' ? $userRoleFilter : null,
                $userRegionalViewFilter !== '' ? $userRegionalViewFilter : null
            ),
            'userTermFilter' => $userTermFilter,
            'userRoleFilter' => $userRoleFilter,
            'userRegionalViewFilter' => $userRegionalViewFilter,
            'userForm' => userFormData($userRepository->findById((int) ($_GET['edit_user'] ?? 0))),
            'userBaseGroupOptions' => $hierarchyRepository->groups(),
        ]);
        break;

    case 'users/save':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('users');
        }

        try {
            $userId = normalize_positive_id($_POST['id'] ?? null);
            $userRepository->save($userId, $_POST);
            flash('success', $userId ? 'Usuário atualizado com sucesso.' : 'Usuário cadastrado com sucesso. Senha inicial definida como o CPF.');
            redirect('users');
        } catch (Throwable $throwable) {
            $userTermFilter = normalize_text($_GET['term'] ?? '');
            $userRoleFilter = strtoupper(normalize_text($_GET['role'] ?? ''));
            $userRegionalViewFilter = strtoupper(normalize_text($_GET['regional_view'] ?? ''));
            flash('error', $throwable->getMessage());
            render('users/index', [
                'title' => 'Usuários',
                'stats' => $userRepository->stats(),
                'users' => $userRepository->all(
                    $userTermFilter !== '' ? $userTermFilter : null,
                    $userRoleFilter !== '' ? $userRoleFilter : null,
                    $userRegionalViewFilter !== '' ? $userRegionalViewFilter : null
                ),
                'userTermFilter' => $userTermFilter,
                'userRoleFilter' => $userRoleFilter,
                'userRegionalViewFilter' => $userRegionalViewFilter,
                'userForm' => userFormDataFromInput($_POST),
                'userBaseGroupOptions' => $hierarchyRepository->groups(),
            ]);
        }
        break;

    case 'users/reset-password':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('users');
        }

        try {
            $userId = (int) ($_GET['id'] ?? 0);
            $userRepository->resetPassword($userId);
            flash('success', 'Senha redefinida com sucesso. A nova senha é o CPF do usuário.');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
        }

        redirect('users');
        break;

    case 'reports':
        render('reports/index', reportsViewData($reportsRepository) + [
            'title' => html_entity_decode('Relat&oacute;rios', ENT_QUOTES, 'UTF-8'),
        ]);
        break;

    case 'post-sales':
        if (is_post()) {
            if (! Csrf::verify($_POST['_token'] ?? null)) {
                flash('error', 'Token de segurança inválido.');
                redirect('post-sales');
            }

            $originalFilename = (string) ($_FILES['xlsx_file']['name'] ?? '');

            try {
                $result = (new PostSaleImportService())->importFromXlsx($_FILES['xlsx_file'] ?? []);

                $postSaleLogRepository->create([
                    'user_id' => (int) Auth::id(),
                    'original_filename' => $originalFilename,
                    'total_rows' => $result['total_rows'] ?? 0,
                    'updated_rows' => $result['updated_rows'] ?? 0,
                    'skipped_rows' => $result['skipped_rows'] ?? 0,
                    'not_found_rows' => $result['not_found_rows'] ?? 0,
                    'status' => 'SUCESSO',
                    'message' => $result['message'] ?? 'Importação concluída.',
                ]);

                flash(
                    'success',
                    sprintf(
                        'Pós-venda atualizado. Total lido: %d | Atualizados: %d | Ignorados: %d | Não encontrados: %d',
                        (int) ($result['total_rows'] ?? 0),
                        (int) ($result['updated_rows'] ?? 0),
                        (int) ($result['skipped_rows'] ?? 0),
                        (int) ($result['not_found_rows'] ?? 0)
                    )
                );
            } catch (Throwable $throwable) {
                $postSaleLogRepository->create([
                    'user_id' => (int) Auth::id(),
                    'original_filename' => $originalFilename,
                    'status' => 'FALHA',
                    'message' => $throwable->getMessage(),
                ]);

                flash('error', $throwable->getMessage());
            }

            redirect('post-sales');
        }

        $filters = postSalesFiltersState($postSaleRepository);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $detailed = $postSaleRepository->detailed($filters, $page, 50);

        render('post_sales/index', [
            'title' => 'Pós Venda',
            'postSalesFilters' => $filters,
            'postSalesPeriods' => $postSaleRepository->availablePeriods(),
            'postSalesRows' => $detailed['items'],
            'postSalesPage' => $detailed['page'],
            'postSalesPerPage' => $detailed['per_page'],
            'postSalesTotal' => $detailed['total'],
            'postSalesTotalPages' => $detailed['total_pages'],
            'postSalesQueryParams' => postSalesQueryParams($filters),
            'postSalesLogs' => $postSaleLogRepository->latest(10),
        ]);
        break;

    case 'reports/download':
        try {
            $reportsFilters = reportsFiltersState($reportsRepository);
            $dataset = $reportsRepository->exportDataset($reportsFilters);
            $rows = $dataset['rows'] ?? [];
            $headers = $dataset['headers'] ?? [];

            if ($rows === [] || $headers === []) {
                flash('error', 'Nenhum registro encontrado para exportar com os filtros selecionados.');
                redirect('reports', reportsQueryParams($reportsFilters));
            }

            (new ReportsExportService())->download($headers, $rows);
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            redirect('reports');
        }
        break;

    case 'products':
        $productTermFilter = normalize_text($_GET['term'] ?? '');
        $productDocumentTypeFilter = normalize_text($_GET['document_type'] ?? '');
        $productCategoryFilter = normalize_text($_GET['category'] ?? '');
        $productVivoTotalFilter = normalize_text($_GET['vivo_total'] ?? '');
        $productCurrentPeriod = $productRepository->currentPeriod();
        $productDuplicateSourcePeriod = $productRepository->duplicateSourcePeriod($productCurrentPeriod);

        render('products/index', [
            'title' => 'Produtos',
            'stats' => $productRepository->stats(),
            'products' => $productRepository->all(
                $productTermFilter !== '' ? $productTermFilter : null,
                $productDocumentTypeFilter !== '' ? $productDocumentTypeFilter : null,
                $productCategoryFilter !== '' ? $productCategoryFilter : null,
                $productVivoTotalFilter !== '' ? $productVivoTotalFilter : null
            ),
            'productTermFilter' => $productTermFilter,
            'productDocumentTypeFilter' => $productDocumentTypeFilter,
            'productCategoryFilter' => $productCategoryFilter,
            'productVivoTotalFilter' => $productVivoTotalFilter,
            'productForm' => productFormData($productRepository->findById((int) ($_GET['edit_product'] ?? 0))),
            'productRecalculationForm' => productRecalculationFormData(),
            'productPeriods' => $productRepository->periods(),
            'productExportPeriodFilter' => normalize_text($_GET['export_period'] ?? ''),
            'productCategories' => $productRepository->categories(),
            'productImportOpen' => (int) ($_GET['import_products'] ?? 0) === 1,
            'productDuplicateCurrentPeriod' => $productCurrentPeriod,
            'productDuplicateSourcePeriod' => $productDuplicateSourcePeriod,
            'productDuplicateOpen' => (int) ($_GET['duplicate_catalog'] ?? 0) === 1,
        ]);
        break;

    case 'products/export':
        try {
            $period = normalize_text($_GET['period'] ?? '');

            if ($period === '') {
                flash('error', 'Selecione um período para baixar o catálogo em XLSX.');
                redirect('products');
            }

            $productsByPeriod = $productRepository->allByPeriod($period);

            if ($productsByPeriod === []) {
                flash('error', 'Nenhum produto encontrado para o período selecionado.');
                redirect('products');
            }

            (new ProductCatalogExportService())->download($period, $productsByPeriod);
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            redirect('products');
        }
        break;

    case 'products/import':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('products', ['import_products' => 1]);
        }

        $uploadedFile = $_FILES['products_file'] ?? null;

        if (! is_array($uploadedFile) || (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Selecione um arquivo XLSX válido para importar.');
            redirect('products', ['import_products' => 1]);
        }

        $originalName = (string) ($uploadedFile['name'] ?? '');
        $tmpFile = (string) ($uploadedFile['tmp_name'] ?? '');

        if ($tmpFile === '' || ! is_uploaded_file($tmpFile)) {
            flash('error', 'Não foi possível receber o arquivo enviado.');
            redirect('products', ['import_products' => 1]);
        }

        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            flash('error', 'Envie uma planilha no formato XLSX.');
            redirect('products', ['import_products' => 1]);
        }

        try {
            $stats = (new ProductCatalogImportService())->importFromXlsx($tmpFile);
            $message = sprintf(
                'Catálogo importado. Processadas: %d | Inseridas: %d | Atualizadas: %d',
                (int) ($stats['processed'] ?? 0),
                (int) ($stats['inserted'] ?? 0),
                (int) ($stats['updated'] ?? 0)
            );

            if ((int) ($stats['duplicate_keys'] ?? 0) > 0) {
                $message .= ' | Chaves repetidas na planilha: ' . (int) $stats['duplicate_keys'];
            }

            flash('success', $message);
            redirect('products');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            redirect('products', ['import_products' => 1]);
        }
        break;

    case 'products/duplicate':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('products', ['duplicate_catalog' => 1]);
        }

        try {
            $duplicateResult = $productRepository->duplicateCatalogToCurrentPeriod();
            flash(
                'success',
                sprintf(
                    "Cat\u{00E1}logo duplicado com sucesso. Origem: %s | Destino: %s | Produtos inseridos: %d",
                    (string) ($duplicateResult['source_period'] ?? '-'),
                    (string) ($duplicateResult['target_period'] ?? '-'),
                    (int) ($duplicateResult['inserted'] ?? 0)
                )
            );
            redirect('products');
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            redirect('products', ['duplicate_catalog' => 1]);
        }
        break;

    case 'products/save':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('products');
        }

        try {
            $productId = normalize_positive_id($_POST['id'] ?? null);
            $syncOptions = productSyncOptionsFromInput($_POST);
            $saveResult = $productRepository->save($productId, $_POST, $syncOptions);
            $syncedSales = (int) ($saveResult['synced_sales'] ?? 0);
            $recalculated = (bool) ($saveResult['recalculated'] ?? false);

            if ($productId !== null) {
                $message = 'Produto atualizado com sucesso.';

                if ($recalculated) {
                    $message .= ' Recálculo executado na sales_nexus.';

                    if ($syncedSales > 0) {
                        $message .= ' ' . $syncedSales . ' venda(s) finalizada(s) foram atualizadas.';
                    } else {
                        $message .= ' Nenhuma venda finalizada foi encontrada na faixa informada.';
                    }
                } else {
                    $message .= ' Vendas finalizadas não foram recalculadas.';
                }

                flash('success', $message);
            } else {
                flash('success', 'Produto cadastrado com sucesso.');
            }

            redirect('products');
        } catch (Throwable $throwable) {
            $productCurrentPeriod = $productRepository->currentPeriod();
            $productDuplicateSourcePeriod = $productRepository->duplicateSourcePeriod($productCurrentPeriod);
            flash('error', $throwable->getMessage());
            render('products/index', [
                'title' => 'Produtos',
                'stats' => $productRepository->stats(),
                'products' => $productRepository->all(
                    normalize_text($_GET['term'] ?? '') !== '' ? normalize_text($_GET['term'] ?? '') : null,
                    normalize_text($_GET['document_type'] ?? '') !== '' ? normalize_text($_GET['document_type'] ?? '') : null,
                    normalize_text($_GET['category'] ?? '') !== '' ? normalize_text($_GET['category'] ?? '') : null,
                    normalize_text($_GET['vivo_total'] ?? '') !== '' ? normalize_text($_GET['vivo_total'] ?? '') : null
                ),
                'productTermFilter' => normalize_text($_GET['term'] ?? ''),
                'productDocumentTypeFilter' => normalize_text($_GET['document_type'] ?? ''),
                'productCategoryFilter' => normalize_text($_GET['category'] ?? ''),
                'productVivoTotalFilter' => normalize_text($_GET['vivo_total'] ?? ''),
                'productForm' => productFormDataFromInput($_POST),
                'productRecalculationForm' => productRecalculationFormDataFromInput($_POST),
                'productPeriods' => $productRepository->periods(),
                'productExportPeriodFilter' => normalize_text($_GET['export_period'] ?? ''),
                'productCategories' => $productRepository->categories(),
                'productImportOpen' => false,
                'productDuplicateCurrentPeriod' => $productCurrentPeriod,
                'productDuplicateSourcePeriod' => $productDuplicateSourcePeriod,
                'productDuplicateOpen' => false,
            ]);
        }
        break;

    case 'queue':
        render('queue/index', queueListingViewData($queueRepository) + [
            'title' => 'Fila de auditoria',
        ]);
        break;

    case 'queue/live':
        header('Content-Type: application/json; charset=UTF-8');
        $queueLiveData = queueListingViewData($queueRepository);
        echo json_encode([
            'summary_html' => view('queue/_summary', [
                'queueSummary' => $queueLiveData['queueSummary'] ?? [],
            ]),
            'list_html' => view('queue/_list', $queueLiveData),
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'queue/consultant-search':
        header('Content-Type: application/json; charset=UTF-8');

        $term = normalize_text($_GET['term'] ?? '');
        $periodHeadcount = clean_document($_GET['period'] ?? '');

        if (mb_strlen($term) < 2) {
            echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
            break;
        }

        $items = array_map(
            static fn(array $seller): array => [
                'id' => (int) $seller['id'],
                'seller_name' => $seller['seller_name'],
                'seller_cpf' => $seller['seller_cpf'],
                'role' => $seller['role'],
                'base_name' => $seller['base_name'],
                'base_group_name' => $seller['base_group_name'],
                'consultant_base_regional' => $seller['CONSULTOR_BASE_REGIONAL'] ?? '',
                'consultant_sector_name' => $seller['CONSULTOR_SETOR_NOME'] ?? '',
                'consultant_sector_type' => $seller['CONSULTOR_SETOR_TIPO'] ?? '',
                'supervisor_cpf' => $seller['SUPERVISOR_CPF'] ?? '',
                'coordinator_cpf' => $seller['COORDENADOR_CPF'] ?? '',
                'territory_manager_name' => $seller['GERENTE_TERRITORIO_NOME'] ?? '',
                'territory_manager_cpf' => $seller['GERENTE_TERRITORIO_CPF'] ?? '',
                'supervisor_name' => $seller['supervisor_name'],
                'coordinator_name' => $seller['coordinator_name'],
                'manager_name' => $seller['manager_name'],
            ],
            $hierarchyRepository->lookupSellers($term, 12, preg_match('/^\d{6}$/', $periodHeadcount) ? $periodHeadcount : null)
        );

        echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
        break;

    case 'queue/claim':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('queue');
        }

        $id = (int) ($_GET['id'] ?? 0);
        $queueContextParams = queueContextParamsFromRequest();
        $queueOperationFilter = (array) ($queueContextParams['operation'] ?? []);
        $queueTermFilter = (string) ($queueContextParams['term'] ?? '');
        $queueItem = $queueRepository->findById($id, currentUserQueueScope(), $queueOperationFilter !== [] ? $queueOperationFilter : null, $queueOperationFilter !== [] || $queueTermFilter !== '');

        if ($queueItem === null) {
            flash('error', 'Venda não encontrada ou fora da sua visão permitida.');
            redirect('queue', $queueContextParams);
        }

        $claimResult = $queueRepository->claim($id, (int) Auth::id(), Auth::isBackofficeSupervisor());
        flash($claimResult['success'] ? 'success' : 'error', $claimResult['message']);

        if ($claimResult['success']) {
            redirect('queue/show', ['id' => $id] + $queueContextParams);
        }

        redirect('queue', $queueContextParams);
        break;

    case 'queue/abandon':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('queue');
        }

        $id = (int) ($_GET['id'] ?? 0);
        $queueContextParams = queueContextParamsFromRequest();
        $queueOperationFilter = (array) ($queueContextParams['operation'] ?? []);
        $queueTermFilter = (string) ($queueContextParams['term'] ?? '');
        $queueItem = $queueRepository->findById($id, currentUserQueueScope(), $queueOperationFilter !== [] ? $queueOperationFilter : null, $queueOperationFilter !== [] || $queueTermFilter !== '');

        if ($queueItem === null) {
            flash('error', 'Venda não encontrada ou fora da sua visão permitida.');
            redirect('queue', $queueContextParams);
        }

        $abandonResult = $queueRepository->abandon($id, (int) Auth::id(), Auth::isBackofficeSupervisor());
        flash($abandonResult['success'] ? 'success' : 'error', $abandonResult['message']);
        redirect('queue', $queueContextParams);
        break;


    case 'queue/comment':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('queue');
        }

        $id = (int) ($_GET['id'] ?? 0);
        $queueContextParams = queueContextParamsFromRequest();
        $queueOperationFilter = (array) ($queueContextParams['operation'] ?? []);
        $queueTermFilter = (string) ($queueContextParams['term'] ?? '');
        $queueItem = $queueRepository->findById($id, currentUserQueueScope(), $queueOperationFilter !== [] ? $queueOperationFilter : null, $queueOperationFilter !== [] || $queueTermFilter !== '');

        if ($queueItem === null) {
            flash('error', 'Venda não encontrada ou fora da sua visão permitida.');
            redirect('queue', $queueContextParams);
        }

        if (($queueItem['audit_status'] ?? '') !== 'FINALIZADA') {
            $claimResult = $queueRepository->claim($id, (int) Auth::id(), Auth::isBackofficeSupervisor());

            if (! $claimResult['success']) {
                flash('error', $claimResult['message']);
                redirect('queue', $queueContextParams);
            }
        }

        $comment = trim((string) ($_POST['comment'] ?? ''));

        if ($comment === '') {
            flash('error', 'Digite um comentário para registrar no log.');
            redirect('queue/show', ['id' => $id] + $queueContextParams);
        }

        if (mb_strlen($comment) > 200) {
            flash('error', 'O comentário deve ter até 200 caracteres.');
            redirect('queue/show', ['id' => $id] + $queueContextParams);
        }

        $saleLogRepository->logComment($queueItem, (int) Auth::id(), $comment);
        flash('success', 'Comentário registrado no log da venda.');
        redirect('queue/show', ['id' => $id] + $queueContextParams);
        break;

    case 'queue/show':
        $id = (int) ($_GET['id'] ?? 0);
        $queueContextParams = queueContextParamsFromRequest();
        $queueOperationFilter = (array) ($queueContextParams['operation'] ?? []);
        $queueTermFilter = (string) ($queueContextParams['term'] ?? '');
        $queueItem = $queueRepository->findById($id, currentUserQueueScope(), $queueOperationFilter !== [] ? $queueOperationFilter : null, $queueOperationFilter !== [] || $queueTermFilter !== '');

        if ($queueItem === null) {
            flash('error', 'Venda não encontrada ou fora da sua visão permitida.');
            redirect('queue', $queueContextParams);
        }

        if (($queueItem['audit_status'] ?? '') !== 'FINALIZADA') {
            $claimResult = $queueRepository->claim($id, (int) Auth::id(), Auth::isBackofficeSupervisor());

            if (! $claimResult['success']) {
                flash('error', $claimResult['message']);
                redirect('queue', $queueContextParams);
            }

            $queueItem = $queueRepository->findById($id, currentUserQueueScope(), $queueOperationFilter !== [] ? $queueOperationFilter : null, $queueOperationFilter !== [] || $queueTermFilter !== '');

            if ($queueItem === null) {
                flash('error', 'Venda não encontrada ou fora da sua visão permitida.');
                redirect('queue', $queueContextParams);
            }
        }

        $catalogContext = resolveCatalogProductContext($queueItem);
        $sellerHierarchy = $hierarchyRepository->findBySellerCpf((string) $queueItem['seller_cpf'], $catalogContext['period']);
        $catalogProducts = $productRepository->allActiveByContext($catalogContext['document_type'], $catalogContext['period']);
        $savedAudit = (new SalesRepository())->findAuditByQueueId($id);

        render('queue/show', [
            'title' => ($queueItem['audit_status'] ?? '') === 'FINALIZADA' ? 'Ver venda' : 'Finalizar venda',
            'queueItem' => $queueItem,
            'queueContextParams' => $queueContextParams,
            'catalogProducts' => $catalogProducts,
            'sellerHierarchy' => $sellerHierarchy,
            'saleLogs' => $saleLogRepository->bySaleCode((string) $queueItem['sale_code']),
            'formData' => defaultAuditFormData($sellerHierarchy, $queueItem, $savedAudit, $catalogProducts),
        ]);
        break;

    case 'queue/save':
        if (! is_post() || ! Csrf::verify($_POST['_token'] ?? null)) {
            flash('error', 'Token de segurança inválido.');
            redirect('queue');
        }

        $id = (int) ($_GET['id'] ?? 0);
        $queueContextParams = queueContextParamsFromRequest();
        $queueOperationFilter = (array) ($queueContextParams['operation'] ?? []);
        $queueTermFilter = (string) ($queueContextParams['term'] ?? '');
        $queueItem = $queueRepository->findById($id, currentUserQueueScope(), $queueOperationFilter !== [] ? $queueOperationFilter : null, $queueOperationFilter !== [] || $queueTermFilter !== '');

        if ($queueItem === null) {
            flash('error', 'Venda não encontrada ou fora da sua visão permitida.');
            redirect('queue', $queueContextParams);
        }

        $catalogContext = resolveCatalogProductContext($queueItem);
        $sellerHierarchy = $hierarchyRepository->findBySellerCpf((string) $queueItem['seller_cpf'], $catalogContext['period']);
        $catalogProducts = $productRepository->allActiveByContext($catalogContext['document_type'], $catalogContext['period']);
        $existingAudit = (new SalesRepository())->findAuditByQueueId($id);
        $formData = collectAuditFormData($_POST);
        $selectedProductIds = array_values(array_filter(array_map('intval', $_POST['product_ids'] ?? [])));
        $selectedProducts = array_values($productRepository->findByIdsInContext($selectedProductIds, $catalogContext['document_type'], $catalogContext['period']));

        if (($queueItem['audit_status'] ?? '') !== 'FINALIZADA') {
            $claimResult = $queueRepository->claim($id, (int) Auth::id(), Auth::isBackofficeSupervisor());

            if (! $claimResult['success']) {
                flash('error', $claimResult['message']);
                redirect('queue', $queueContextParams);
            }
        }

        $validationError = validateAuditSubmission($formData, $selectedProducts, $selectedProductIds, $catalogContext['document_type']);

        if ($validationError !== null) {
            flash('error', $validationError);
            render('queue/show', [
                'title' => ($queueItem['audit_status'] ?? '') === 'FINALIZADA' ? 'Ver venda' : 'Finalizar venda',
                'queueItem' => $queueItem,
                'queueContextParams' => $queueContextParams,
                'catalogProducts' => $catalogProducts,
                'sellerHierarchy' => $sellerHierarchy,
                'saleLogs' => $saleLogRepository->bySaleCode((string) $queueItem['sale_code']),
                'formData' => $formData + ['product_ids' => $selectedProductIds],
            ]);
            return;
        }

        try {
            $salesRepository = new SalesRepository();
            $salesRepository->saveAudit(
                $queueItem,
                $formData,
                array_values($selectedProducts),
                (int) Auth::id(),
                (string) (Auth::user()['name'] ?? ''),
                auditFormChangeSet(
                    defaultAuditFormData($sellerHierarchy, $queueItem, $existingAudit, $catalogProducts),
                    $formData,
                    array_values($selectedProducts)
                )
            );
            flash(
                'success',
                ($queueItem['audit_status'] ?? '') === 'FINALIZADA'
                    ? 'Venda atualizada com sucesso'
                    : 'Venda finalizada com sucesso'
            );
            redirect('queue', $queueContextParams);
        } catch (Throwable $throwable) {
            flash('error', $throwable->getMessage());
            render('queue/show', [
                'title' => ($queueItem['audit_status'] ?? '') === 'FINALIZADA' ? 'Ver venda' : 'Finalizar venda',
                'queueItem' => $queueItem,
                'queueContextParams' => $queueContextParams,
                'catalogProducts' => $catalogProducts,
                'sellerHierarchy' => $sellerHierarchy,
                'saleLogs' => $saleLogRepository->bySaleCode((string) $queueItem['sale_code']),
                'formData' => $formData + ['product_ids' => $selectedProductIds],
            ]);
        }
        break;

    default:
        http_response_code(404);
        render('dashboard/index', dashboardViewData($queueRepository, $batchRepository, $dashboardAnalyticsRepository) + [
            'title' => 'Página não encontrada',
        ]);
        break;
}

function defaultAuditFormData(
    ?array $sellerHierarchy = null,
    ?array $queueItem = null,
    ?array $savedAudit = null,
    array $catalogProducts = []
): array {
    $resolvedSaleType = '';
    $serviceOrder = '';
    $consultantCpf = '';

    if ($queueItem !== null) {
        $resolvedSaleType = normalize_text($queueItem['sale_customer_type'] ?? '');
        $serviceOrder = normalize_text($queueItem['service_order_number'] ?? '');
        $consultantCpf = clean_document($queueItem['seller_cpf'] ?? '');

        if ($resolvedSaleType === '') {
            $resolvedSaleType = normalize_text($queueItem['customer_document_type'] ?? '');
        }
    }

    $data = [
        'pdv_adabas' => normalize_text($queueItem['pdv_adabas'] ?? ''),
        'service_type' => normalize_text($queueItem['service_type'] ?? ''),
        'cabinet_code' => normalize_text($queueItem['cabinet_code'] ?? ''),
        'customer_document_type' => normalize_text($queueItem['customer_document_type'] ?? ''),
        'customer_birth_date' => '',
        'customer_mother_name' => '',
        'customer_document' => clean_document($queueItem['customer_document'] ?? ''),
        'customer_name' => normalize_text($queueItem['customer_name'] ?? ''),
        'phone_mobile' => normalize_text($queueItem['phone_mobile'] ?? ''),
        'phone_alt_1' => normalize_text($queueItem['phone_alt_1'] ?? ''),
        'phone_alt_2' => normalize_text($queueItem['phone_alt_2'] ?? ''),
        'customer_email' => normalize_text($queueItem['customer_email'] ?? ''),
        'customer_regional' => normalize_text($queueItem['customer_regional'] ?? ''),
        'service_name' => normalize_text($queueItem['service_name'] ?? ''),
        'plan_name' => normalize_text($queueItem['plan_name'] ?? ''),
        'package_name' => normalize_text($queueItem['package_name'] ?? ''),
        'composition_name' => normalize_text($queueItem['composition_name'] ?? ''),
        'sale_regional' => normalize_text($queueItem['sale_regional'] ?? ''),
        'ddd' => normalize_text($queueItem['ddd'] ?? ''),
        'service_order' => $serviceOrder,
        'sale_instance' => '',
        'sale_protocol' => '',
        'post_sale_status' => 'PENDENTE',
        'installation_date' => '',
        'post_sale_sub_status' => 'PENDENTE',
        'audit_installation_date' => '',
        'customer_cep' => clean_document($queueItem['customer_cep'] ?? ''),
        'customer_street_name' => '',
        'customer_street_number' => '',
        'customer_street_complement' => '',
        'customer_neighborhood' => '',
        'customer_city' => normalize_text($queueItem['customer_city'] ?? ''),
        'customer_uf' => normalize_text($queueItem['customer_uf'] ?? ''),
        'consultant_name' => $sellerHierarchy['seller_name'] ?? '',
        'consultant_cpf' => $sellerHierarchy['seller_cpf'] ?? $consultantCpf,
        'consultant_type' => $sellerHierarchy['role'] ?? '',
        'consultant_base_name' => $sellerHierarchy['base_name'] ?? '',
        'consultant_base_group' => $sellerHierarchy['base_group_name'] ?? '',
        'consultant_base_regional' => $sellerHierarchy['CONSULTOR_BASE_REGIONAL'] ?? ($queueItem['sale_regional'] ?? ''),
        'consultant_sector_name' => $sellerHierarchy['CONSULTOR_SETOR_NOME'] ?? '',
        'consultant_sector_type' => $sellerHierarchy['CONSULTOR_SETOR_TIPO'] ?? '',
        'supervisor_name' => $sellerHierarchy['supervisor_name'] ?? '',
        'supervisor_cpf' => $sellerHierarchy['SUPERVISOR_CPF'] ?? '',
        'coordinator_name' => $sellerHierarchy['coordinator_name'] ?? '',
        'coordinator_cpf' => $sellerHierarchy['COORDENADOR_CPF'] ?? '',
        'base_manager_name' => $sellerHierarchy['manager_name'] ?? '',
        'territory_manager_name' => $sellerHierarchy['GERENTE_TERRITORIO_NOME'] ?? '',
        'territory_manager_cpf' => $sellerHierarchy['GERENTE_TERRITORIO_CPF'] ?? '',
        'director_name' => '',
        'sale_type' => $resolvedSaleType,
        'product_ids' => [],
        'product_names' => [],
    ];

    if ($savedAudit !== null) {
        $data['customer_birth_date'] = normalize_date_to_db($savedAudit['customer_birth_date'] ?? '') ?? $data['customer_birth_date'];
        $data['customer_mother_name'] = normalize_text($savedAudit['customer_mother_name'] ?? '') ?: $data['customer_mother_name'];
        $data['phone_mobile'] = normalize_text($savedAudit['phone_mobile'] ?? '') ?: $data['phone_mobile'];
        $data['service_order'] = normalize_text($savedAudit['service_order'] ?? '') ?: $data['service_order'];
        $data['sale_instance'] = normalize_text($savedAudit['sale_instance'] ?? '') ?: $data['sale_instance'];
        $data['sale_protocol'] = normalize_text($savedAudit['sale_protocol'] ?? '') ?: $data['sale_protocol'];
        $data['audit_installation_date'] = normalize_date_to_db($savedAudit['audit_installation_date'] ?? '') ?? '';
        $data['customer_street_name'] = normalize_text($savedAudit['customer_street_name'] ?? '') ?: $data['customer_street_name'];
        $data['customer_street_number'] = normalize_text($savedAudit['customer_street_number'] ?? '') ?: $data['customer_street_number'];
        $data['customer_street_complement'] = normalize_text($savedAudit['customer_street_complement'] ?? '') ?: $data['customer_street_complement'];
        $data['customer_neighborhood'] = normalize_text($savedAudit['customer_neighborhood'] ?? '') ?: $data['customer_neighborhood'];
        $data['customer_city'] = normalize_text($savedAudit['customer_city'] ?? '') ?: $data['customer_city'];
        $data['customer_uf'] = normalize_text($savedAudit['customer_uf'] ?? '') ?: $data['customer_uf'];
        $data['consultant_name'] = normalize_text($savedAudit['consultant_name'] ?? '') ?: $data['consultant_name'];
        $data['consultant_cpf'] = clean_document($savedAudit['consultant_cpf'] ?? '') ?: $data['consultant_cpf'];
        $data['consultant_type'] = normalize_text($savedAudit['consultant_type'] ?? '') ?: $data['consultant_type'];
        $data['consultant_base_name'] = normalize_text($savedAudit['consultant_base_name'] ?? '') ?: $data['consultant_base_name'];
        $data['consultant_base_group'] = normalize_text($savedAudit['consultant_base_group'] ?? '') ?: $data['consultant_base_group'];
        $data['consultant_base_regional'] = normalize_text($savedAudit['consultant_base_regional'] ?? '') ?: $data['consultant_base_regional'];
        $data['consultant_sector_name'] = normalize_text($savedAudit['consultant_sector_name'] ?? '') ?: $data['consultant_sector_name'];
        $data['consultant_sector_type'] = normalize_text($savedAudit['consultant_sector_type'] ?? '') ?: $data['consultant_sector_type'];
        $data['supervisor_name'] = normalize_text($savedAudit['supervisor_name'] ?? '') ?: $data['supervisor_name'];
        $data['coordinator_name'] = normalize_text($savedAudit['coordinator_name'] ?? '') ?: $data['coordinator_name'];
        $data['base_manager_name'] = normalize_text($savedAudit['base_manager_name'] ?? '') ?: $data['base_manager_name'];
        $data['territory_manager_name'] = normalize_text($savedAudit['territory_manager_name'] ?? '') ?: $data['territory_manager_name'];
        $data['sale_type'] = normalize_text($savedAudit['sale_type'] ?? '') ?: $data['sale_type'];
        $data['product_names'] = array_values(array_filter(array_map(
            static fn(mixed $name): string => normalize_text((string) $name),
            $savedAudit['product_names'] ?? []
        )));
        $data['product_ids'] = resolveAuditProductIds($savedAudit['product_names'] ?? [], $catalogProducts);
    }

    return $data;
}

function collectAuditFormData(array $input): array
{
    return [
        'pdv_adabas' => normalize_text($input['pdv_adabas'] ?? ''),
        'service_type' => normalize_text($input['service_type'] ?? ''),
        'cabinet_code' => normalize_text($input['cabinet_code'] ?? ''),
        'customer_document_type' => normalize_text($input['customer_document_type'] ?? ''),
        'customer_birth_date' => normalize_date_to_db($input['customer_birth_date'] ?? ''),
        'customer_mother_name' => normalize_text($input['customer_mother_name'] ?? ''),
        'customer_document' => clean_document($input['customer_document'] ?? ''),
        'customer_name' => normalize_text($input['customer_name'] ?? ''),
        'phone_mobile' => substr(clean_document($input['phone_mobile'] ?? ''), 0, 11),
        'phone_alt_1' => substr(clean_document($input['phone_alt_1'] ?? ''), 0, 11),
        'phone_alt_2' => substr(clean_document($input['phone_alt_2'] ?? ''), 0, 11),
        'customer_email' => normalize_text($input['customer_email'] ?? ''),
        'customer_regional' => normalize_text($input['customer_regional'] ?? ''),
        'service_name' => normalize_text($input['service_name'] ?? ''),
        'plan_name' => normalize_text($input['plan_name'] ?? ''),
        'package_name' => normalize_text($input['package_name'] ?? ''),
        'composition_name' => normalize_text($input['composition_name'] ?? ''),
        'sale_regional' => normalize_text($input['sale_regional'] ?? ''),
        'ddd' => normalize_text($input['ddd'] ?? ''),
        'service_order' => normalize_text($input['service_order'] ?? ''),
        'sale_instance' => normalize_text($input['sale_instance'] ?? ''),
        'sale_protocol' => normalize_text($input['sale_protocol'] ?? ''),
        'post_sale_status' => 'PENDENTE',
        'installation_date' => null,
        'post_sale_sub_status' => 'PENDENTE',
        'audit_installation_date' => normalize_date_to_db($input['audit_installation_date'] ?? ''),
        'customer_cep' => clean_document($input['customer_cep'] ?? ''),
        'customer_street_name' => normalize_text($input['customer_street_name'] ?? ''),
        'customer_street_number' => normalize_text($input['customer_street_number'] ?? ''),
        'customer_street_complement' => normalize_text($input['customer_street_complement'] ?? ''),
        'customer_neighborhood' => normalize_text($input['customer_neighborhood'] ?? ''),
        'customer_city' => normalize_text($input['customer_city'] ?? ''),
        'customer_uf' => mb_strtoupper(normalize_text($input['customer_uf'] ?? '')),
        'consultant_name' => normalize_text($input['consultant_name'] ?? ''),
        'consultant_cpf' => clean_document($input['consultant_cpf'] ?? ''),
        'consultant_type' => normalize_text($input['consultant_type'] ?? ''),
        'consultant_base_name' => normalize_text($input['consultant_base_name'] ?? ''),
        'consultant_base_group' => normalize_text($input['consultant_base_group'] ?? ''),
        'consultant_base_regional' => normalize_text($input['consultant_base_regional'] ?? ''),
        'consultant_sector_name' => normalize_text($input['consultant_sector_name'] ?? ''),
        'consultant_sector_type' => normalize_text($input['consultant_sector_type'] ?? ''),
        'supervisor_name' => normalize_text($input['supervisor_name'] ?? ''),
        'coordinator_name' => normalize_text($input['coordinator_name'] ?? ''),
        'base_manager_name' => normalize_text($input['base_manager_name'] ?? ''),
        'territory_manager_name' => normalize_text($input['territory_manager_name'] ?? ''),
        'director_name' => normalize_text($input['director_name'] ?? ''),
        'sale_type' => normalize_text($input['sale_type'] ?? ''),
        'product_ids' => array_values(array_filter(array_map('intval', $input['product_ids'] ?? []))),
    ];
}

function validateAuditSubmission(
    array $formData,
    array $selectedProducts,
    array $selectedProductIds = [],
    ?string $expectedProductDocumentType = null
): ?string {
    if (normalize_date_to_db($formData['customer_birth_date'] ?? '') === null) {
        return 'Preencha a data de nascimento no resumo da venda.';
    }

    if (normalize_text($formData['customer_mother_name'] ?? '') === '') {
        return 'Preencha o nome da mãe ou nome fantasia no resumo da venda.';
    }

    if (normalize_text($formData['service_order'] ?? '') === '') {
        return 'Preencha a ordem de serviço para continuar.';
    }

    if (normalize_date_to_db($formData['audit_installation_date'] ?? '') === null) {
        return 'Preencha a data de agendamento da instalação.';
    }

    if (strlen(clean_document($formData['customer_cep'] ?? '')) !== 8) {
        return 'Informe um CEP válido com 8 números.';
    }

    if (normalize_text($formData['customer_street_name'] ?? '') === '') {
        return 'Preencha o logradouro da venda.';
    }

    if (normalize_text($formData['customer_street_number'] ?? '') === '') {
        return 'Preencha o número do endereço.';
    }

    if (normalize_text($formData['customer_neighborhood'] ?? '') === '') {
        return 'Preencha o bairro da venda.';
    }

    if (mb_strlen(normalize_text($formData['customer_uf'] ?? '')) !== 2) {
        return 'Informe a UF com 2 letras.';
    }

    if (normalize_text($formData['customer_city'] ?? '') === '') {
        return 'Preencha a cidade da venda.';
    }

    if (normalize_text($formData['consultant_name'] ?? '') === '') {
        return 'Informe o consultor responsável pela venda.';
    }

    if (strlen(clean_document($formData['consultant_cpf'] ?? '')) !== 11) {
        return 'Selecione um consultor válido para a venda.';
    }

    if (normalize_text($formData['consultant_type'] ?? '') === '') {
        return 'Preencha o tipo do consultor.';
    }

    if (normalize_text($formData['consultant_base_name'] ?? '') === '') {
        return 'Preencha a base nome do consultor.';
    }

    if (normalize_text($formData['consultant_base_group'] ?? '') === '') {
        return 'Preencha a base grupo do consultor.';
    }

    if (normalize_text($formData['supervisor_name'] ?? '') === '') {
        return 'Preencha o supervisor responsável.';
    }

    if (normalize_text($formData['coordinator_name'] ?? '') === '') {
        return 'Preencha o coordenador responsável.';
    }

    if (normalize_text($formData['base_manager_name'] ?? '') === '') {
        return 'Preencha o gerente base responsável.';
    }

    if ($selectedProducts === []) {
        return 'Selecione ao menos um produto para finalizar a venda.';
    }

    $uniqueSelectedIds = array_values(array_unique(array_map('intval', $selectedProductIds)));

    if ($selectedProductIds !== [] && count($uniqueSelectedIds) !== count($selectedProductIds)) {
        return 'Não é permitido selecionar o mesmo produto mais de uma vez.';
    }

    if ($uniqueSelectedIds !== [] && count($selectedProducts) !== count($uniqueSelectedIds)) {
        return $expectedProductDocumentType !== null
            ? 'Selecione apenas produtos do tipo ' . $expectedProductDocumentType . ' para esta venda.'
            : 'Um ou mais produtos selecionados são inválidos.';
    }

    $fixedDataProducts = 0;
    $fixedVoiceProducts = 0;
    $fixedTvProducts = 0;
    $mobileProducts = 0;
    $additionalProducts = 0;
    $fixedRequiresVivoTotalMobile = false;
    $mobileHasNonVivoTotal = false;
    $selectedFixedVivoTotalName = null;
    $selectedMobileProducts = [];
    $normalizeProductMatchName = static function (string $value): string {
        $value = preg_replace('/\s*\([^)]*\)/u', '', $value) ?? $value;
        return strtoupper(normalize_text($value));
    };

    foreach ($selectedProducts as $product) {
        $productType = strtoupper(normalize_text((string) ($product['TIPO'] ?? $product['type'] ?? '')));
        $productCategory = strtoupper(normalize_text((string) ($product['CATEGORIA'] ?? $product['category'] ?? '')));
        $productVivoTotal = strtoupper(normalize_text((string) ($product['VIVO_TOTAL'] ?? $product['vivo_total'] ?? '')));

        if (in_array($productType, ['ADICIONAL', 'SVA'], true)) {
            $additionalProducts++;
            continue;
        }

        if ($productCategory === 'MOVEL') {
            $mobileProducts++;
            $selectedMobileProducts[] = [
                'name' => $normalizeProductMatchName((string) ($product['PRODUTO_NOME'] ?? $product['name'] ?? '')),
                'vivo_total' => $productVivoTotal,
            ];

            if ($productVivoTotal !== 'TOTAL') {
                $mobileHasNonVivoTotal = true;
            }

            continue;
        }

        if ($productType === 'VOZ') {
            $fixedVoiceProducts++;
            continue;
        }

        if ($productType === 'TV') {
            $fixedTvProducts++;
            continue;
        }

        $fixedDataProducts++;

        if ($productVivoTotal === 'TOTAL') {
            $fixedRequiresVivoTotalMobile = true;
            $selectedFixedVivoTotalName = $normalizeProductMatchName((string) ($product['PRODUTO_NOME'] ?? $product['name'] ?? ''));
        }
    }

    if ($fixedDataProducts > 1) {
        return 'Selecione apenas 1 produto de FIXA - DADOS.';
    }

    if ($fixedVoiceProducts > 1) {
        return 'Selecione apenas 1 produto de FIXA - VOZ.';
    }

    if ($fixedTvProducts > 1) {
        return 'Selecione apenas 1 produto de FIXA - TV.';
    }

    if ($mobileProducts > 1) {
        return 'Selecione apenas 1 produto de MÓVEL.';
    }

    if ($additionalProducts > 10) {
        return 'Selecione no máximo 10 serviços adicionais.';
    }

    if ($fixedRequiresVivoTotalMobile && $mobileHasNonVivoTotal) {
        return 'Ao selecionar uma FIXA Vivo Total, o produto de MÓVEL também precisa ser Vivo Total.';
    }

    if ($fixedRequiresVivoTotalMobile && $selectedFixedVivoTotalName !== null && $selectedMobileProducts !== []) {
        $expectedMobileProductName = str_replace('FIXA', 'MOVEL', $selectedFixedVivoTotalName);

        foreach ($selectedMobileProducts as $mobileProduct) {
            if (($mobileProduct['name'] ?? '') !== $expectedMobileProductName) {
                return 'Ao selecionar uma FIXA Vivo Total, escolha o produto correspondente de MÓVEL.';
            }
        }
    }

    return null;
}

function resolveCatalogProductContext(array $queueItem): array
{
    $customerDocumentType = strtoupper(normalize_text($queueItem['customer_document_type'] ?? ''));
    $documentType = in_array($customerDocumentType, ['B2B', 'B2C'], true) ? $customerDocumentType : null;

    $saleCustomerType = strtoupper(normalize_text($queueItem['sale_customer_type'] ?? ''));
    if ($documentType === null && in_array($saleCustomerType, ['B2B', 'B2C'], true)) {
        $documentType = $saleCustomerType;
    }

    $inputDate = normalize_date_to_db((string) ($queueItem['sale_input_date'] ?? ''));
    $period = $inputDate !== null ? date('Ym', strtotime($inputDate)) : null;

    return [
        'document_type' => $documentType,
        'period' => $period,
    ];
}

function auditFormChangeSet(array $originalFormData, array $currentFormData, array $products): array
{
    $trackedFields = [
        'pdv_adabas' => 'PDV Adabas',
        'service_type' => 'Modalidade do serviço',
        'cabinet_code' => 'CNL AT',
        'customer_birth_date' => 'Data de nascimento',
        'customer_mother_name' => 'Mãe / Nome fantasia',
        'customer_document' => 'Documento',
        'customer_name' => 'Cliente',
        'phone_mobile' => 'Celular',
        'phone_alt_1' => 'Telefone 1',
        'phone_alt_2' => 'Telefone 2',
        'customer_email' => 'E-mail',
        'customer_regional' => 'Regional cliente',
        'service_name' => 'Serviço',
        'plan_name' => 'Plano',
        'package_name' => 'Pacote',
        'composition_name' => 'Composição',
        'sale_regional' => 'Regional',
        'ddd' => 'DDD',
        'sale_type' => 'Tipo de venda',
        'service_order' => 'Ordem de serviço',
        'sale_instance' => 'Instância',
        'sale_protocol' => 'Protocolo',
        'audit_installation_date' => 'Data Agendamento Instalação',
        'customer_cep' => 'CEP',
        'customer_street_name' => 'Logradouro',
        'customer_street_number' => 'Número',
        'customer_street_complement' => 'Complemento',
        'customer_neighborhood' => 'Bairro',
        'customer_city' => 'Cidade',
        'customer_uf' => 'UF',
        'consultant_name' => 'Consultor nome',
        'consultant_cpf' => 'Consultor CPF',
        'consultant_type' => 'Consultor tipo',
        'consultant_base_name' => 'Consultor base nome',
        'consultant_base_group' => 'Consultor base grupo',
        'consultant_base_regional' => 'Consultor base regional',
        'consultant_sector_name' => 'Consultor setor nome',
        'consultant_sector_type' => 'Consultor setor tipo',
        'supervisor_name' => 'Supervisor nome',
        'coordinator_name' => 'Coordenador nome',
        'base_manager_name' => 'Gerente base nome',
        'territory_manager_name' => 'Gerente território nome',
    ];
    $changes = [];

    foreach ($trackedFields as $field => $label) {
        $originalValue = auditLogComparableValue($field, $originalFormData[$field] ?? null);
        $currentValue = auditLogComparableValue($field, $currentFormData[$field] ?? null);

        if ($originalValue === $currentValue) {
            continue;
        }

        $changes[] = [
            'field' => $label,
            'from' => auditLogDisplayValue($field, $originalFormData[$field] ?? null),
            'to' => auditLogDisplayValue($field, $currentFormData[$field] ?? null),
        ];
    }

    $selectedProductNames = array_values(array_filter(array_map(
        static fn(array $product): string => normalize_text((string) ($product['name'] ?? '')),
        $products
    )));
    $originalProductIds = array_values(array_map('intval', $originalFormData['product_ids'] ?? []));
    $currentProductIds = array_values(array_map(
        static fn(array $product): int => (int) ($product['id'] ?? 0),
        $products
    ));
    sort($originalProductIds);
    sort($currentProductIds);

    if ($currentProductIds !== $originalProductIds) {
        $changes[] = [
            'field' => 'Produtos',
            'from' => auditProductChangeLabel($originalFormData['product_names'] ?? []),
            'to' => implode(', ', $selectedProductNames),
        ];
    }

    return $changes;
}

function auditLogComparableValue(string $field, mixed $value): string
{
    if ($field === 'customer_cep') {
        return clean_document((string) $value);
    }

    if (in_array($field, ['audit_installation_date', 'customer_birth_date'], true)) {
        return normalize_date_to_db((string) $value) ?? '';
    }

    return normalize_text((string) $value);
}

function auditLogDisplayValue(string $field, mixed $value): string
{
    if (in_array($field, ['audit_installation_date', 'customer_birth_date'], true)) {
        $date = normalize_date_to_db((string) $value);

        return $date !== null ? format_date_br($date) : 'Não informado';
    }

    $text = normalize_text((string) $value);

    return $text !== '' ? $text : 'Não informado';
}

function resolveAuditProductIds(array $productNames, array $catalogProducts): array
{
    if ($productNames === [] || $catalogProducts === []) {
        return [];
    }

    $catalogMap = [];

    foreach ($catalogProducts as $product) {
        $catalogMap[ascii_key((string) ($product['name'] ?? ''))] = (int) ($product['id'] ?? 0);
    }

    $productIds = [];

    foreach ($productNames as $productName) {
        $normalizedKey = ascii_key((string) $productName);
        $productId = $catalogMap[$normalizedKey] ?? null;

        if ($productId !== null) {
            $productIds[] = $productId;
        }
    }

    return array_values(array_unique($productIds));
}

function auditProductChangeLabel(array $originalProductNames): string
{
    $names = array_values(array_filter(array_map(
        static fn(mixed $name): string => normalize_text((string) $name),
        $originalProductNames
    )));

    return $names !== [] ? implode(', ', array_values(array_unique($names))) : 'Nenhum produto selecionado';
}

function hierarchyBaseFormData(?array $base): array
{
    return [
        'id' => $base['id'] ?? '',
        'name' => $base['name'] ?? '',
        'regional' => $base['REGIONAL'] ?? 'I',
    ];
}

function hierarchyGroupFormData(?array $group): array
{
    return [
        'id' => $group['id'] ?? '',
        'name' => $group['name'] ?? '',
        'base_id' => $group['base_id'] ?? '',
    ];
}

function hierarchySellerFormData(?array $seller): array
{
    return [
        'id' => $seller['id'] ?? '',
        'seller_name' => $seller['seller_name'] ?? '',
        'seller_cpf' => $seller['seller_cpf'] ?? '',
        'period_headcount' => $seller['PERIODO_HEADCOUNT'] ?? '202603',
        'role' => $seller['role'] ?? 'CONSULTOR',
        'supervisor_name' => $seller['supervisor_name'] ?? '',
        'supervisor_cpf' => $seller['SUPERVISOR_CPF'] ?? '',
        'coordinator_name' => $seller['coordinator_name'] ?? '',
        'coordinator_cpf' => $seller['COORDENADOR_CPF'] ?? '',
        'manager_name' => $seller['manager_name'] ?? '',
        'manager_cpf' => $seller['GERENTE_BASE_CPF'] ?? '',
        'consultant_base_regional' => $seller['CONSULTOR_BASE_REGIONAL'] ?? '',
        'consultant_sector_name' => $seller['CONSULTOR_SETOR_NOME'] ?? '',
        'consultant_sector_type' => $seller['CONSULTOR_SETOR_TIPO'] ?? '',
        'territory_manager_name' => $seller['GERENTE_TERRITORIO_NOME'] ?? '',
        'territory_manager_cpf' => $seller['GERENTE_TERRITORIO_CPF'] ?? '',
        'base_id' => $seller['base_id'] ?? '',
        'base_group_id' => $seller['base_group_id'] ?? '',
    ];
}

function userFormData(?array $user): array
{
    return [
        'id' => $user['id'] ?? '',
        'name' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'cpf' => $user['cpf'] ?? '',
        'role' => $user['role'] ?? 'BACKOFFICE',
        'regional_view' => $user['regional_view'] ?? 'FULL',
        'base_group_scope_ids' => array_values(array_map(
            'intval',
            $user['base_group_scope_ids'] ?? []
        )),
        'is_active' => isset($user['is_active']) ? (int) $user['is_active'] : 1,
    ];
}

function productFormData(?array $product): array
{
    return [
        'id' => $product['id'] ?? '',
        'product_name' => $product['PRODUTO_NOME'] ?? '',
        'managerial_value' => $product['PRODUTO_VALOR_GERENCIAL'] ?? '',
        'commercial_score' => $product['PRODUTO_PONTUACAO_COMERCIAL'] ?? '',
        'factor' => $product['FATOR'] ?? '',
        'category' => $product['CATEGORIA'] ?? '',
        'type' => $product['TIPO'] ?? '',
        'vivo_total' => $product['VIVO_TOTAL'] ?? '',
        'document_type' => $product['TIPO_DOC'] ?? 'B2C',
        'solo' => $product['SOLO'] ?? '',
        'two_p' => $product['2P'] ?? '',
        'three_p' => $product['3P'] ?? '',
        'duo' => $product['DUO'] ?? '',
        'period' => $product['PERIODO'] ?? '',
        'active' => isset($product['ativo']) ? (int) $product['ativo'] : 1,
    ];
}

function productRecalculationFormData(): array
{
    return [
        'save_mode' => 'save_only',
        'date_from' => null,
        'date_to' => null,
        'date_range_label' => dateRangeLabel(),
        'is_open' => false,
    ];
}

function productFormDataFromInput(array $input): array
{
    return [
        'id' => normalize_positive_id($input['id'] ?? null) ?? '',
        'product_name' => mb_strtoupper(normalize_text($input['product_name'] ?? '')),
        'managerial_value' => normalize_text($input['managerial_value'] ?? ''),
        'commercial_score' => normalize_text($input['commercial_score'] ?? ''),
        'factor' => normalize_text($input['factor'] ?? ''),
        'category' => mb_strtoupper(normalize_text($input['category'] ?? '')),
        'type' => mb_strtoupper(normalize_text($input['type'] ?? '')),
        'vivo_total' => mb_strtoupper(normalize_text($input['vivo_total'] ?? '')),
        'document_type' => strtoupper(normalize_text($input['document_type'] ?? 'B2C')),
        'solo' => mb_strtoupper(normalize_text($input['solo'] ?? '')),
        'two_p' => mb_strtoupper(normalize_text($input['two_p'] ?? '')),
        'three_p' => mb_strtoupper(normalize_text($input['three_p'] ?? '')),
        'duo' => mb_strtoupper(normalize_text($input['duo'] ?? '')),
        'period' => clean_document($input['period'] ?? ''),
        'active' => (int) (($input['active'] ?? 1) === '0' ? 0 : 1),
    ];
}

function productRecalculationFormDataFromInput(array $input): array
{
    $dateFrom = normalize_date_to_db($input['recalculate_from'] ?? null);
    $dateTo = normalize_date_to_db($input['recalculate_to'] ?? null);

    if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    $saveMode = normalize_text($input['save_mode'] ?? 'save_only');
    $isOpen = $saveMode === 'recalculate' || $dateFrom !== null || $dateTo !== null;

    return [
        'save_mode' => $saveMode === 'recalculate' ? 'recalculate' : 'save_only',
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'date_range_label' => dateRangeLabel($dateFrom, $dateTo),
        'is_open' => $isOpen,
    ];
}

function productSyncOptionsFromInput(array $input): array
{
    $dateFrom = normalize_date_to_db($input['recalculate_from'] ?? null);
    $dateTo = normalize_date_to_db($input['recalculate_to'] ?? null);

    if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    return [
        'mode' => normalize_text($input['save_mode'] ?? 'save_only') === 'recalculate' ? 'recalculate' : 'save_only',
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ];
}

function userFormDataFromInput(array $input): array
{
    return [
        'id' => normalize_positive_id($input['id'] ?? null) ?? '',
        'name' => mb_strtoupper(normalize_text($input['name'] ?? '')),
        'email' => mb_strtolower(normalize_text($input['email'] ?? '')),
        'cpf' => clean_document($input['cpf'] ?? ''),
        'role' => strtoupper(normalize_text($input['role'] ?? 'BACKOFFICE')),
        'regional_view' => strtoupper(normalize_text($input['regional_view'] ?? 'FULL')),
        'base_group_scope_ids' => array_values(array_unique(array_filter(array_map(
            'intval',
            $input['base_group_scope_ids'] ?? []
        ), static fn(int $baseGroupId): bool => $baseGroupId > 0))),
        'is_active' => (int) (($input['is_active'] ?? 1) === '0' ? 0 : 1),
    ];
}

function normalize_positive_id(mixed $value): ?int
{
    $id = (int) $value;

    return $id > 0 ? $id : null;
}

function normalize_positive_ids(mixed $value): array
{
    return array_values(array_unique(array_filter(array_map(
        'intval',
        (array) $value
    ), static fn(int $id): bool => $id > 0)));
}

function normalize_allowed_upper_values(mixed $value, array $allowed): array
{
    return array_values(array_unique(array_filter(array_map(
        static fn(mixed $item): string => strtoupper(normalize_text((string) $item)),
        (array) $value
    ), static fn(string $item): bool => in_array($item, $allowed, true))));
}

function dateRangeLabel(?string $dateFrom = null, ?string $dateTo = null): string
{
    if ($dateFrom !== null && $dateTo !== null) {
        return $dateFrom === $dateTo
            ? format_date_br($dateFrom)
            : format_date_br($dateFrom) . ' ate ' . format_date_br($dateTo);
    }

    if ($dateFrom !== null) {
        return format_date_br($dateFrom);
    }

    if ($dateTo !== null) {
        return format_date_br($dateTo);
    }

    return 'Selecionar data ou intervalo';
}

function dashboardContextParamsFromRequest(): array
{
    return [
        'customer_type' => normalize_text($_GET['customer_type'] ?? '') !== '' ? normalize_text($_GET['customer_type'] ?? '') : null,
        'operation' => normalize_text($_GET['operation'] ?? '') !== '' ? normalize_text($_GET['operation'] ?? '') : null,
        'date_from' => normalize_date_to_db($_GET['date_from'] ?? null),
        'date_to' => normalize_date_to_db($_GET['date_to'] ?? null),
        'executive' => (int) ($_GET['executive'] ?? 0) === 1 ? 1 : null,
    ];
}

function dashboardViewData(
    ImportQueueRepository $queueRepository,
    ImportBatchRepository $batchRepository,
    DashboardAnalyticsRepository $dashboardAnalyticsRepository
): array {
    $regionalScope = currentUserQueueScope();
    $dashboardContext = dashboardContextParamsFromRequest();
    $customerTypeFilter = (string) ($dashboardContext['customer_type'] ?? '');
    $operationFilter = (string) ($dashboardContext['operation'] ?? '');
    $rawDateFromFilter = $dashboardContext['date_from'];
    $rawDateToFilter = $dashboardContext['date_to'];
    $dashboardExecutiveAllowed = Auth::hasRole('ADMINISTRADOR', 'BACKOFFICE SUPERVISOR');
    $dashboardExecutiveMode = $dashboardExecutiveAllowed && (int) ($dashboardContext['executive'] ?? 0) === 1;
    $hasExplicitDateFilter = $rawDateFromFilter !== null || $rawDateToFilter !== null;
    $dateFromFilter = $rawDateFromFilter;
    $dateToFilter = $rawDateToFilter;

    if ($dateFromFilter !== null && $dateToFilter !== null && $dateFromFilter > $dateToFilter) {
        [$dateFromFilter, $dateToFilter] = [$dateToFilter, $dateFromFilter];
    }

    if (! $hasExplicitDateFilter) {
        $today = date('Y-m-d');
        $dateFromFilter = $today;
        $dateToFilter = $today;
    }

    $operations = $queueRepository->dashboardOperations($regionalScope);

    if ($operationFilter !== '' && ! in_array($operationFilter, $operations, true)) {
        $operations[] = $operationFilter;
        sort($operations);
    }

    $dashboardStats = $queueRepository->dashboardStats(
        $customerTypeFilter !== '' ? $customerTypeFilter : null,
        $dateFromFilter,
        $dateToFilter,
        $operationFilter !== '' ? $operationFilter : null,
        $regionalScope
    );
    $dashboardSalesKpis = $dashboardAnalyticsRepository->kpisOverview(
        $customerTypeFilter !== '' ? $customerTypeFilter : null,
        $dateFromFilter,
        $dateToFilter,
        $operationFilter !== '' ? $operationFilter : null,
        $regionalScope
    );

    $dashboardExecutiveData = $dashboardExecutiveMode
        ? $dashboardAnalyticsRepository->executiveOverview(
            $customerTypeFilter !== '' ? $customerTypeFilter : null,
            $dateFromFilter,
            $dateToFilter,
            $operationFilter !== '' ? $operationFilter : null,
            $regionalScope
        )
        : null;

    $dashboardStats['completed'] = (int) ($dashboardSalesKpis['finalized_sales'] ?? 0);

    return [
        'stats' => $dashboardStats,
        'recentBatches' => $batchRepository->recent(5),
        'dashboardCustomerTypeFilter' => $customerTypeFilter,
        'dashboardOperationFilter' => $operationFilter,
        'dashboardDateFromFilter' => $dateFromFilter,
        'dashboardDateToFilter' => $dateToFilter,
        'dashboardDateRangeLabel' => dateRangeLabel($dateFromFilter, $dateToFilter),
        'dashboardFiltersOpen' => $customerTypeFilter !== '' || $operationFilter !== '' || $hasExplicitDateFilter,
        'dashboardOperations' => $operations,
        'dashboardExecutiveAllowed' => $dashboardExecutiveAllowed,
        'dashboardExecutiveMode' => $dashboardExecutiveMode,
        'dashboardExecutiveData' => $dashboardExecutiveData,
        'dashboardContextParams' => $dashboardContext,
        'dashboardLiveUrl' => url('dashboard/live', array_filter(array_merge($dashboardContext, ['executive' => $dashboardExecutiveMode ? 1 : null]))),
        'dashboardExecutiveToggleUrl' => url('dashboard', array_filter(array_merge($dashboardContext, ['executive' => $dashboardExecutiveMode ? null : 1]))),
        'dashboardExecutiveExitUrl' => url('dashboard', array_filter(array_merge($dashboardContext, ['executive' => null]))),
    ];
}

function queueContextParamsFromRequest(): array
{
    $statusValues = array_values(array_filter(array_unique(array_map(
        static fn(mixed $status): string => strtoupper(normalize_text((string) $status)),
        (array) ($_GET['status'] ?? [])
    )), static fn(string $status): bool => in_array($status, ['PENDENTE INPUT', 'AUDITANDO', 'FINALIZADA'], true)));

    if ($statusValues === []) {
        $legacyStatus = strtoupper(normalize_text($_GET['status'] ?? ''));

        if (in_array($legacyStatus, ['PENDENTE INPUT', 'AUDITANDO', 'FINALIZADA'], true)) {
            $statusValues = [$legacyStatus];
        }
    }

    $modalityValues = array_values(array_filter(array_unique(array_map(
        static fn(mixed $modality): string => normalize_text((string) $modality),
        (array) ($_GET['modality'] ?? [])
    )), static fn(string $modality): bool => $modality !== ''));
    $operationValues = array_values(array_filter(array_unique(array_map(
        static fn(mixed $operation): string => normalize_text((string) $operation),
        (array) ($_GET['operation'] ?? [])
    )), static fn(string $operation): bool => $operation !== ''));

    return [
        'status' => $statusValues !== [] ? $statusValues : null,
        'customer_type' => normalize_text($_GET['customer_type'] ?? '') !== '' ? normalize_text($_GET['customer_type'] ?? '') : null,
        'modality' => $modalityValues !== [] ? $modalityValues : null,
        'operation' => $operationValues !== [] ? $operationValues : null,
        'term' => normalize_text($_GET['term'] ?? '') !== '' ? normalize_text($_GET['term'] ?? '') : null,
        'date_from' => normalize_date_to_db($_GET['date_from'] ?? null),
        'date_to' => normalize_date_to_db($_GET['date_to'] ?? null),
    ];
}

function queueListingViewData(ImportQueueRepository $queueRepository): array
{
    $regionalScope = currentUserQueueScope();
    $statusFilter = (array) (queueContextParamsFromRequest()['status'] ?? []);
    $termFilter = normalize_text($_GET['term'] ?? '');
    $customerTypeFilter = normalize_text($_GET['customer_type'] ?? '');
    $modalityFilter = array_values(array_filter(array_map(
        static fn(mixed $modality): string => normalize_text((string) $modality),
        (array) ($_GET['modality'] ?? [])
    ), static fn(string $modality): bool => $modality !== ''));
    $operationFilter = array_values(array_filter(array_map(
        static fn(mixed $operation): string => normalize_text((string) $operation),
        (array) (queueContextParamsFromRequest()['operation'] ?? [])
    ), static fn(string $operation): bool => $operation !== ''));
    $dateFromFilter = normalize_date_to_db($_GET['date_from'] ?? null);
    $dateToFilter = normalize_date_to_db($_GET['date_to'] ?? null);
    $summaryDateFrom = $dateFromFilter;
    $summaryDateTo = $dateToFilter;

    if ($dateFromFilter !== null && $dateToFilter !== null && $dateFromFilter > $dateToFilter) {
        [$dateFromFilter, $dateToFilter] = [$dateToFilter, $dateFromFilter];
    }

    if ($summaryDateFrom !== null && $summaryDateTo !== null && $summaryDateFrom > $summaryDateTo) {
        [$summaryDateFrom, $summaryDateTo] = [$summaryDateTo, $summaryDateFrom];
    }

    if ($summaryDateFrom === null && $summaryDateTo === null) {
        $today = date('Y-m-d');
        $summaryDateFrom = $today;
        $summaryDateTo = $today;
    }

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
    $pendingPriorCount = $queueRepository->pendingBeforeDate($today, $regionalScope);
    $prioritizeDateFrom = $pendingPriorCount > 0
        ? $queueRepository->oldestPendingBeforeDate($today, $regionalScope)
        : null;
    $hasPendingFilter = in_array('PENDENTE INPUT', $statusFilter, true);
    $hasOlderDateTo = $dateToFilter !== null && $dateToFilter < $today;
    $showPrioritizeModal = $pendingPriorCount > 0 && ! ($hasPendingFilter && $hasOlderDateTo);

    $pageNumber = max(1, (int) ($_GET['page'] ?? 1));
    $queueResult = $queueRepository->search(
        $statusFilter !== [] ? $statusFilter : null,
        $termFilter !== '' ? $termFilter : null,
        $customerTypeFilter !== '' ? $customerTypeFilter : null,
        $modalityFilter !== [] ? $modalityFilter : null,
        $dateFromFilter,
        $dateToFilter,
        $operationFilter !== [] ? $operationFilter : null,
        $pageNumber,
        50,
        $regionalScope
    );
    $queueSummary = $queueRepository->summary(
        $statusFilter !== [] ? $statusFilter : null,
        $termFilter !== '' ? $termFilter : null,
        $customerTypeFilter !== '' ? $customerTypeFilter : null,
        $modalityFilter !== [] ? $modalityFilter : null,
        $summaryDateFrom,
        $summaryDateTo,
        $operationFilter !== [] ? $operationFilter : null,
        $regionalScope
    );

    $operations = $queueRepository->dashboardOperations();
    $modalities = $queueRepository->queueModalities();

    foreach ($operationFilter as $selectedOperation) {
        if (! in_array($selectedOperation, $operations, true)) {
            $operations[] = $selectedOperation;
        }
    }

    sort($operations);

    foreach ($modalityFilter as $selectedModality) {
        if (! in_array($selectedModality, $modalities, true)) {
            $modalities[] = $selectedModality;
        }
    }

    sort($modalities);

    return [
        'statusFilter' => $statusFilter,
        'termFilter' => $termFilter,
        'customerTypeFilter' => $customerTypeFilter,
        'modalityFilter' => $modalityFilter,
        'operationFilter' => $operationFilter,
        'queueOperations' => $operations,
        'queueModalities' => $modalities,
        'dateFromFilter' => $dateFromFilter,
        'dateToFilter' => $dateToFilter,
        'items' => $queueResult['items'],
        'queueSummary' => $queueSummary,
        'currentPage' => $queueResult['page'],
        'totalPages' => $queueResult['total_pages'],
        'totalItems' => $queueResult['total'],
        'perPage' => $queueResult['per_page'],
        'pendingPriorCount' => $pendingPriorCount,
        'prioritizeDateFrom' => $prioritizeDateFrom,
        'prioritizeDateTo' => $yesterday,
        'showPrioritizeModal' => $showPrioritizeModal,
    ];
}

function currentUserQueueScope(): ?array
{
    $user = Auth::user();

    if ($user === null) {
        return null;
    }

    if (($user['role'] ?? '') === 'ADMINISTRADOR') {
        return null;
    }

    $regionalView = strtoupper(normalize_text($user['regional_view'] ?? 'FULL'));

    if (in_array($regionalView, ['I', 'II'], true)) {
        return [
            'mode' => 'REGIONAL',
            'regional' => $regionalView,
            'base_group_ids' => [],
        ];
    }

    if ($regionalView === 'PERSONALIZADO') {
        return [
            'mode' => 'PERSONALIZADO',
            'regional' => null,
            'base_group_ids' => array_values(array_filter(array_map(
                'intval',
                $user['base_group_scope_ids'] ?? []
            ), static fn(int $baseGroupId): bool => $baseGroupId > 0)),
        ];
    }

    return null;
}

function userRegionalViewLabel(array $user): string
{
    $regionalView = strtoupper(normalize_text($user['regional_view'] ?? 'FULL'));

    if ($regionalView !== 'PERSONALIZADO') {
        return $regionalView !== '' ? $regionalView : 'FULL';
    }

    $scopeCount = (int) ($user['base_group_scope_count'] ?? 0);

    return $scopeCount > 0
        ? 'PERSONALIZADO (' . $scopeCount . ')'
        : 'PERSONALIZADO';
}

function postSalesDefaultFilters(PostSaleRepository $postSaleRepository): array
{
    $latestPeriod = $postSaleRepository->latestPeriod();

    return [
        'period' => $latestPeriod,
        'term' => '',
    ];
}

function postSalesHasRequestFilters(array $source): bool
{
    foreach (['period', 'term', 'apply', 'clear'] as $key) {
        if (array_key_exists($key, $source)) {
            return true;
        }
    }

    return false;
}

function postSalesNormalizeFilters(array $source, PostSaleRepository $postSaleRepository): array
{
    $defaults = postSalesDefaultFilters($postSaleRepository);
    $availablePeriods = $postSaleRepository->availablePeriods();
    $period = trim((string) ($source['period'] ?? $defaults['period'] ?? ''));

    if ($period !== '' && ! in_array($period, $availablePeriods, true)) {
        $period = $defaults['period'] ?? '';
    }

    return [
        'period' => $period,
        'term' => substr(trim((string) ($source['term'] ?? $defaults['term'] ?? '')), 0, 120),
    ];
}

function postSalesFiltersState(PostSaleRepository $postSaleRepository): array
{
    $sessionKey = 'post_sales_filters';

    if ((int) ($_GET['clear'] ?? 0) === 1) {
        $_SESSION[$sessionKey] = postSalesDefaultFilters($postSaleRepository);
    } elseif (postSalesHasRequestFilters($_GET)) {
        $_SESSION[$sessionKey] = postSalesNormalizeFilters($_GET, $postSaleRepository);
    } elseif (! isset($_SESSION[$sessionKey]) || ! is_array($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = postSalesDefaultFilters($postSaleRepository);
    }

    return postSalesNormalizeFilters((array) $_SESSION[$sessionKey], $postSaleRepository);
}

function postSalesQueryParams(array $filters, ?int $page = null): array
{
    return array_filter([
        'period' => $filters['period'] ?? null,
        'term' => $filters['term'] ?? null,
        'page' => $page,
    ], static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
}

function reportsDefaultFilters(ReportsRepository $reportsRepository): array
{
    $today = new DateTimeImmutable('today');
    $availableYears = $reportsRepository->availableYears();
    $currentYear = (int) $today->format('Y');

    if (! in_array($currentYear, $availableYears, true)) {
        $availableYears[] = $currentYear;
        rsort($availableYears);
    }

    return [
        'year' => $currentYear,
        'months' => [$today->format('m')],
        'day' => $today->format('d'),
        'statuses' => [],
        'sub_statuses' => [],
        'operations' => [],
        'base_groups' => [],
        'customer_types' => [],
        'supervisors' => [],
        'coordinators' => [],
        'managers' => [],
        'consultants' => [],
        'territories' => [],
        'term' => '',
        'date_from' => $today->format('Y-m-d'),
        'date_to' => $today->format('Y-m-d'),
    ];
}

function reportsHasRequestFilters(array $source): bool
{
    foreach (
        [
            'year',
            'month',
            'day',
            'status',
            'sub_status',
            'operation',
            'base_group',
            'customer_type',
            'supervisor',
            'coordinator',
            'manager',
            'consultant',
            'territory',
            'term',
            'apply',
            'clear',
        ] as $key
    ) {
        if (array_key_exists($key, $source)) {
            return true;
        }
    }

    return false;
}

function reportsNormalizeMultiValues(array $rawValues, array $allowedValues): array
{
    $allowedMap = [];

    foreach ($allowedValues as $allowedValue) {
        $normalizedKey = normalize_text((string) $allowedValue);

        if ($normalizedKey !== '') {
            $allowedMap[$normalizedKey] = (string) $allowedValue;
        }
    }

    $normalizedValues = [];

    foreach ($rawValues as $rawValue) {
        $normalizedKey = normalize_text((string) $rawValue);

        if ($normalizedKey === '' || ! array_key_exists($normalizedKey, $allowedMap)) {
            continue;
        }

        $normalizedValues[$allowedMap[$normalizedKey]] = $allowedMap[$normalizedKey];
    }

    return array_values($normalizedValues);
}

function reportsNormalizeFilters(array $source, ReportsRepository $reportsRepository): array
{
    $defaults = reportsDefaultFilters($reportsRepository);
    $options = $reportsRepository->filterOptions();
    $availableYears = $reportsRepository->availableYears();
    $availableMonths = array_map(
        static fn(int $month): string => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
        range(1, 12)
    );
    $year = (int) ($source['year'] ?? $defaults['year']);

    if (! in_array($year, $availableYears, true)) {
        $year = (int) $defaults['year'];
    }

    $monthsSource = $source['month'] ?? $source['months'] ?? $defaults['months'] ?? [];
    $months = reportsNormalizeMultiValues((array) $monthsSource, $availableMonths);
    $day = preg_match('/^(0[1-9]|[12][0-9]|3[01])$/', (string) ($source['day'] ?? ''))
        ? (string) $source['day']
        : '';

    $dateFrom = null;
    $dateTo = null;

    if ($months !== []) {
        $sortedMonths = array_map('intval', $months);
        sort($sortedMonths);
        $firstMonth = str_pad((string) $sortedMonths[0], 2, '0', STR_PAD_LEFT);
        $lastMonth = str_pad((string) $sortedMonths[count($sortedMonths) - 1], 2, '0', STR_PAD_LEFT);

        if ($day !== '' && checkdate((int) $firstMonth, (int) $day, $year)) {
            $dateFrom = sprintf('%04d-%02d-%02d', $year, (int) $firstMonth, (int) $day);
        } else {
            $monthStart = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, (int) $firstMonth));

            if ($monthStart !== false) {
                $dateFrom = $monthStart->format('Y-m-d');
            }
        }

        if ($day !== '' && checkdate((int) $lastMonth, (int) $day, $year)) {
            $dateTo = sprintf('%04d-%02d-%02d', $year, (int) $lastMonth, (int) $day);
        } else {
            $monthEnd = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, (int) $lastMonth));

            if ($monthEnd !== false) {
                $dateTo = $monthEnd->modify('last day of this month')->format('Y-m-d');
            }
        }
    } elseif ($day !== '') {
        $dateFrom = sprintf('%04d-01-%02d', $year, (int) $day);
        $dateTo = sprintf('%04d-12-%02d', $year, (int) $day);
    } else {
        $dateFrom = sprintf('%04d-01-01', $year);
        $dateTo = sprintf('%04d-12-31', $year);
    }

    return [
        'year' => $year,
        'months' => $months,
        'day' => $day,
        'statuses' => reportsNormalizeMultiValues((array) ($source['status'] ?? $source['statuses'] ?? []), $options['statuses'] ?? []),
        'sub_statuses' => reportsNormalizeMultiValues((array) ($source['sub_status'] ?? $source['sub_statuses'] ?? []), $options['sub_statuses'] ?? []),
        'operations' => reportsNormalizeMultiValues((array) ($source['operation'] ?? $source['operations'] ?? []), $options['operations'] ?? []),
        'base_groups' => reportsNormalizeMultiValues((array) ($source['base_group'] ?? $source['base_groups'] ?? []), $options['base_groups'] ?? []),
        'customer_types' => reportsNormalizeMultiValues((array) ($source['customer_type'] ?? $source['customer_types'] ?? []), $options['customer_types'] ?? []),
        'supervisors' => reportsNormalizeMultiValues((array) ($source['supervisor'] ?? $source['supervisors'] ?? []), $options['supervisors'] ?? []),
        'coordinators' => reportsNormalizeMultiValues((array) ($source['coordinator'] ?? $source['coordinators'] ?? []), $options['coordinators'] ?? []),
        'managers' => reportsNormalizeMultiValues((array) ($source['manager'] ?? $source['managers'] ?? []), $options['managers'] ?? []),
        'consultants' => reportsNormalizeMultiValues((array) ($source['consultant'] ?? $source['consultants'] ?? []), $options['consultants'] ?? []),
        'territories' => reportsNormalizeMultiValues((array) ($source['territory'] ?? $source['territories'] ?? []), $options['territories'] ?? []),
        'term' => substr(trim((string) ($source['term'] ?? $defaults['term'] ?? '')), 0, 120),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ];
}

function reportsFiltersState(ReportsRepository $reportsRepository): array
{
    $sessionKey = 'reports_filters';

    if ((int) ($_GET['clear'] ?? 0) === 1) {
        $_SESSION[$sessionKey] = reportsDefaultFilters($reportsRepository);
    } elseif (reportsHasRequestFilters($_GET)) {
        $_SESSION[$sessionKey] = reportsNormalizeFilters($_GET, $reportsRepository);
    } elseif (! isset($_SESSION[$sessionKey]) || ! is_array($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = reportsDefaultFilters($reportsRepository);
    }

    return reportsNormalizeFilters((array) $_SESSION[$sessionKey], $reportsRepository);
}

function reportsQueryParams(array $filters, ?int $page = null): array
{
    return array_filter([
        'year' => $filters['year'] ?? null,
        'month' => $filters['months'] ?? $filters['month'] ?? null,
        'day' => $filters['day'] ?? null,
        'status' => $filters['statuses'] ?? null,
        'sub_status' => $filters['sub_statuses'] ?? null,
        'operation' => $filters['operations'] ?? null,
        'base_group' => $filters['base_groups'] ?? null,
        'customer_type' => $filters['customer_types'] ?? null,
        'supervisor' => $filters['supervisors'] ?? null,
        'coordinator' => $filters['coordinators'] ?? null,
        'manager' => $filters['managers'] ?? null,
        'consultant' => $filters['consultants'] ?? null,
        'territory' => $filters['territories'] ?? null,
        'term' => $filters['term'] ?? null,
        'page' => $page,
    ], static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
}

function reportsViewData(ReportsRepository $reportsRepository): array
{
    $filters = reportsFiltersState($reportsRepository);
    $reportsOptions = $reportsRepository->filterOptionsForFilters($filters);
    $filters = reportsAutoSelectFilters($filters, $reportsOptions);
    $reportsOptions = $reportsRepository->filterOptionsForFilters($filters);
    $detailed = $reportsRepository->detailed($filters, max(1, (int) ($_GET['page'] ?? 1)), 50);

    return [
        'reportsFilters' => $filters,
        'reportsOptions' => $reportsOptions,
        'reportsYears' => $reportsRepository->availableYears(),
        'reportsOverview' => $reportsRepository->overview($filters),
        'reportsByOperation' => $reportsRepository->summaryByOperation($filters),
        'reportsByBaseGroup' => $reportsRepository->summaryByBaseGroup($filters),
        'reportsRows' => $detailed['items'],
        'reportsPage' => $detailed['page'],
        'reportsPerPage' => $detailed['per_page'],
        'reportsTotal' => $detailed['total'],
        'reportsTotalPages' => $detailed['total_pages'],
        'reportsUpdatedAt' => $reportsRepository->latestDataTimestamp($filters),
        'reportsQueryParams' => reportsQueryParams($filters),
    ];
}

function reportsAutoSelectFilters(array $filters, array $options): array
{
    $map = [
        'operations' => 'operations',
        'base_groups' => 'base_groups',
        'customer_types' => 'customer_types',
        'supervisors' => 'supervisors',
        'coordinators' => 'coordinators',
        'managers' => 'managers',
        'consultants' => 'consultants',
        'territories' => 'territories',
    ];

    foreach ($map as $filterKey => $optionKey) {
        $selected = (array) ($filters[$filterKey] ?? []);
        if ($selected !== []) {
            continue;
        }

        $available = array_values(array_filter(array_map(
            static fn(mixed $value): string => trim((string) $value),
            (array) ($options[$optionKey] ?? [])
        ), static fn(string $value): bool => $value !== ''));

        if (count($available) === 1) {
            $filters[$filterKey] = [$available[0]];
        }
    }

    return $filters;
}
