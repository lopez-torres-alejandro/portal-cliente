<?php
/** Client data endpoints. */

/* ==============================================================
 * GET-ALL: Reconstruir JSON completo desde tablas relacionales
 * ============================================================== */
function handleGetAll(): void {
    $db = getDB();

    // Dev brand
    $devBrand = ['name' => 'Alejandro Lopez', 'tagline' => 'Desarrollo Web & Tecnología'];

    // All clients with nested data
    $clients = [];
    if ($_SESSION['role'] === 'admin') {
        $rc = $db->query("SELECT id, company, email, ruc, phone, since, verified FROM clients ORDER BY id");
    } else {
        $rc = $db->prepare("SELECT id, company, email, ruc, phone, since, verified FROM clients WHERE id = ?");
        $rc->execute([(int)$_SESSION['client_id']]);
    }
    while ($c = $rc->fetch()) {
        $c['id'] = (int)$c['id'];
        $c['since'] = $c['since'] ?? date('Y-m-d');
        $c['verified'] = $c['verified'] === 't' || $c['verified'] === true;
        $projects = [];
        $rp = $db->prepare("SELECT id, name, progress, phase FROM projects WHERE client_id = ? ORDER BY id");
        $rp->execute([$c['id']]);
        while ($p = $rp->fetch()) {
            $p['id'] = (int)$p['id'];
            $p['progress'] = (int)$p['progress'];

            // Milestones
            $milestones = [];
            $rm = $db->prepare("SELECT id, title, status FROM milestones WHERE project_id = ? ORDER BY position");
            $rm->execute([$p['id']]);
            while ($m = $rm->fetch()) {
                $m['id'] = (int)$m['id'];
                $milestones[] = $m;
            }

            // Services
            $services = [];
            $rs = $db->prepare("SELECT id, name, status, due_date, price FROM services WHERE project_id = ?");
            $rs->execute([$p['id']]);
            while ($s = $rs->fetch()) {
                $s['id'] = (int)$s['id'];
                $s['dueDate'] = $s['due_date'];
                unset($s['due_date']);
                $services[] = $s;
            }

            // Brand
            $brand = ['palette' => [], 'logoUrl' => '#', 'brandManualUrl' => '#', 'repoUrl' => '#'];
            $rb = $db->prepare("SELECT palette, logo_url, brand_manual_url, repo_url FROM brand_resources WHERE project_id = ?");
            $rb->execute([$p['id']]);
            if ($b = $rb->fetch()) {
                $brand['palette'] = json_decode($b['palette'], true) ?? [];
                $brand['logoUrl'] = $b['logo_url'] ?? '#';
                $brand['brandManualUrl'] = $b['brand_manual_url'] ?? '#';
                $brand['repoUrl'] = $b['repo_url'] ?? '#';
            }

            $p['milestones'] = $milestones;
            $p['services'] = $services;
            $p['brand'] = $brand;
            $projects[] = $p;
        }
        $c['projects'] = $projects;
        $clients[] = $c;
    }

    jsonResponse([
        'dev_brand' => $devBrand,
        'clients' => $clients
    ]);
}

/* ==============================================================
 * SAVE-CLIENTS: Reemplazar todos los datos
 * ============================================================== */
function handleSaveClients(): void {
    requireRole(['admin']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método no permitido', 405);
    $input = requestJson();
    if (!isset($input['clients']) || !is_array($input['clients']) || !array_is_list($input['clients']) || count($input['clients']) < 1 || count($input['clients']) > 500) {
        jsonError('Se requiere una lista de clientes válida');
    }
    validateClientsPayload($input['clients']);

    $db = getDB();
    $savedPasswords = [];
    foreach ($db->query("SELECT id, password FROM clients") as $row) {
        $savedPasswords[(string)$row['id']] = $row['password'];
    }
    $db->beginTransaction();
    try {
        $db->exec("DELETE FROM milestones");
        $db->exec("DELETE FROM services");
        $db->exec("DELETE FROM brand_resources");
        $db->exec("DELETE FROM projects");
        $db->exec("DELETE FROM clients");

        $ic = $db->prepare("INSERT INTO clients (id, company, email, ruc, phone, since, verified, password) VALUES (?,?,?,?,?,?,?,?)");
        $ip = $db->prepare("INSERT INTO projects (id, client_id, name, progress, phase) VALUES (?,?,?,?,?)");
        $im = $db->prepare("INSERT INTO milestones (id, project_id, title, status, position) VALUES (?,?,?,?,?)");
        $is = $db->prepare("INSERT INTO services (id, project_id, name, status, due_date, price) VALUES (?,?,?,?,?,?)");
        $ib = $db->prepare("INSERT INTO brand_resources (project_id, palette, logo_url, brand_manual_url, repo_url) VALUES (?,?,?,?,?)");

        $nextMsId = 1;
        $nextSvcId = 1;

        foreach ($input['clients'] as $c) {
            $verified = !empty($c['verified']) ? 'true' : 'false';
            $passwordHash = $savedPasswords[(string)$c['id']] ?? '';
            $ic->execute([$c['id'], $c['company'], $c['email'] ?? '', $c['ruc'] ?? '', $c['phone'] ?? '', $c['since'] ?? date('Y-m-d'), $verified, $passwordHash]);
            foreach ($c['projects'] as $pi => $p) {
                $ip->execute([$p['id'], $c['id'], $p['name'], (int)($p['progress'] ?? 0), $p['phase'] ?? '']);
                $pos = 0;
                foreach ($p['milestones'] ?? [] as $m) {
                    $im->execute([$m['id'] ?? $nextMsId++, $p['id'], $m['title'], $m['status'] ?? 'pending', $pos++]);
                }
                foreach ($p['services'] ?? [] as $s) {
                    $dueDate = trim((string)($s['dueDate'] ?? ''));
                    $is->execute([$s['id'] ?? $nextSvcId++, $p['id'], $s['name'], $s['status'] ?? 'pending', $dueDate === '' ? null : $dueDate, $s['price'] ?? '']);
                }
                $brand = $p['brand'] ?? [];
                $palette = json_encode($brand['palette'] ?? [], JSON_UNESCAPED_UNICODE);
                $ib->execute([$p['id'], $palette, $brand['logoUrl'] ?? '#', $brand['brandManualUrl'] ?? '#', $brand['repoUrl'] ?? '#']);
            }
        }

        foreach (['clients', 'projects', 'milestones', 'services'] as $table) {
            $db->exec("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), EXISTS(SELECT 1 FROM {$table}))");
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    jsonResponse(['message' => 'Clientes guardados correctamente']);
}

function validateClientsPayload(array $clients): void {
    foreach ($clients as $client) {
        if (!is_array($client)
            || !isset($client['id'], $client['company'], $client['email'])
            || !is_int($client['id']) || $client['id'] < 1
            || !is_string($client['company']) || trim($client['company']) === '' || strlen($client['company']) > 255
            || !is_string($client['email']) || strlen($client['email']) > 255 || !filter_var($client['email'], FILTER_VALIDATE_EMAIL)
            || (isset($client['ruc']) && (!is_string($client['ruc']) || strlen($client['ruc']) > 20))
            || (isset($client['phone']) && (!is_string($client['phone']) || strlen($client['phone']) > 50))
            || !is_array($client['projects'] ?? null) || !array_is_list($client['projects'])) {
            jsonError('Los datos de un cliente no son válidos');
        }

        foreach ($client['projects'] as $project) {
            if (!is_array($project)
                || !isset($project['id'], $project['name'])
                || !is_int($project['id']) || $project['id'] < 1
                || !is_string($project['name']) || trim($project['name']) === '' || strlen($project['name']) > 255
                || (isset($project['phase']) && !is_string($project['phase']))
                || (isset($project['progress']) && (!is_numeric($project['progress']) || $project['progress'] < 0 || $project['progress'] > 100))
                || !is_array($project['milestones'] ?? []) || !array_is_list($project['milestones'] ?? [])
                || !is_array($project['services'] ?? []) || !array_is_list($project['services'] ?? [])) {
                jsonError('Los datos de un proyecto no son válidos');
            }

            foreach ($project['milestones'] ?? [] as $milestone) {
                if (!is_array($milestone) || !isset($milestone['title']) || !is_string($milestone['title'])) {
                    jsonError('Los hitos del proyecto no son válidos');
                }
            }
            foreach ($project['services'] ?? [] as $service) {
                if (!is_array($service) || !isset($service['name']) || !is_string($service['name'])) {
                    jsonError('Los servicios del proyecto no son válidos');
                }
                if (isset($service['dueDate']) && !is_string($service['dueDate'])) {
                    jsonError('La fecha de un servicio no es válida');
                }
            }
        }
    }
}
