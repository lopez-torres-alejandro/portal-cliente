<?php
/** Demo dataset used only by the explicit CLI setup option. */

function seedDemoClients(PDO $db) {
    $clients = [
        ['company' => 'Virgo Moto Sport', 'email' => 'contacto@virgomotosport.com', 'ruc' => '20456789012', 'since' => '2026-03-15', 'projects' => [
            ['name' => 'E-commerce + Catálogo Interactivo', 'progress' => 65, 'phase' => 'Maquetación y Conexión de Base de Datos',
             'ms' => [['title' => 'Reunión de diagnóstico e información inicial', 'status' => 'completed', 'pos' => 1],
                      ['title' => 'Prototipo e interfaz de usuario (UI)', 'status' =>'completed', 'pos' => 2],
                      ['title' => 'Programación del catálogo interactivo y WhatsApp', 'status' =>'in-progress', 'pos' => 3],
                      ['title' => 'Pruebas de rendimiento y optimización móvil', 'status' =>'pending', 'pos' => 4],
                      ['title' => 'Configuración de Dominio, Hosting y Lanzamiento', 'status' =>'pending', 'pos' => 5]],
             'sv' => [['name' => 'Diseño Web Profesional', 'status' =>'in-progress', 'due' => '2026-08-15', 'price' => '$2,500'],
                      ['name' => 'Mantenimiento Mensual', 'status' =>'active', 'due' => '2026-07-31', 'price' => '$150/mes'],
                      ['name' => 'SEO On-Page Básico', 'status' =>'about-to-expire', 'due' => '2026-07-10', 'price' => '$350'],
                      ['name' => 'Certificado SSL', 'status' =>'active', 'due' => '2027-03-01', 'price' => '$45/año']],
             'br' => [['name' => 'Naranja Principal', 'hex' =>'#FF5722'],['name' => 'Negro Carbón', 'hex' =>'#1A1A2E'],['name' => 'Gris Oscuro', 'hex' =>'#4A4A4A'],['name' => 'Blanco', 'hex' =>'#FFFFFF'],['name' => 'Rojo Acento', 'hex' =>'#E53935']]],
            ['name' => 'Sistema de Inventarios', 'progress' => 10, 'phase' => 'Reunión de Requerimientos',
             'ms' => [['title' => 'Diagnóstico de necesidades', 'status' =>'in-progress', 'pos' => 1],['title' => 'Diseño de base de datos', 'status' =>'pending', 'pos' => 2],['title' => 'Desarrollo del módulo de inventario', 'status' =>'pending', 'pos' => 3],['title' => 'Pruebas y despliegue', 'status' =>'pending', 'pos' => 4]],
             'sv' => [['name' => 'Hosting + Dominio .com', 'status' =>'active', 'due' => '2027-01-20', 'price' => '$120/año']],
             'br' => [['name' => 'Azul Técnico', 'hex' =>'#1976D2'],['name' => 'Gris Pizarra', 'hex' =>'#455A64'],['name' => 'Blanco', 'hex' =>'#FFFFFF']]]
        ]],
        ['company' => 'TechSolutions Perú', 'email' => 'info@techsolutions.pe', 'ruc' => '20512345678', 'since' => '2026-04-01', 'projects' => [
            ['name' => 'Plataforma SaaS Intra-empresarial', 'progress' => 30, 'phase' => 'Prototipo y Definición de Features',
             'ms' => [['title' => 'Reunión de requerimientos y alcance', 'status' =>'completed', 'pos' => 1],['title' => 'Arquitectura del sistema y base de datos', 'status' =>'in-progress', 'pos' => 2],['title' => 'Desarrollo del MVP funcional', 'status' =>'pending', 'pos' => 3],['title' => 'Pruebas beta y correcciones', 'status' =>'pending', 'pos' => 4],['title' => 'Despliegue en producción', 'status' =>'pending', 'pos' => 5]],
             'sv' => [['name' => 'Desarrollo de Software a Medida', 'status' =>'in-progress', 'due' => '2026-09-30', 'price' => '$8,000'],['name' => 'Hosting Cloud + Dominio .pe', 'status' =>'active', 'due' => '2027-04-01', 'price' => '$240/año']],
             'br' => [['name' => 'Azul Corporativo', 'hex' =>'#1565C0'],['name' => 'Gris Plata', 'hex' =>'#9E9E9E'],['name' => 'Blanco', 'hex' =>'#FFFFFF']]]
        ]],
        ['company' => 'GreenLeaf Organics', 'email' => 'hola@greenleaf.pe', 'ruc' => '20698765432', 'since' => '2026-05-10', 'projects' => [
            ['name' => 'Tienda Online + Blog de Recetas', 'progress' => 15, 'phase' => 'Reunión de Diagnóstico Inicial',
             'ms' => [['title' => 'Reunión de diagnóstico e información inicial', 'status' =>'in-progress', 'pos' => 1],['title' => 'Diseño de interfaz y experiencia de usuario', 'status' =>'pending', 'pos' => 2],['title' => 'Programación de tienda y pasarela de pago', 'status' =>'pending', 'pos' => 3],['title' => 'Pruebas de calidad y carga', 'status' =>'pending', 'pos' => 4],['title' => 'Configuración de dominio y lanzamiento', 'status' =>'pending', 'pos' => 5]],
             'sv' => [['name' => 'Diseño Web Profesional', 'status' =>'in-progress', 'due' => '2026-08-01', 'price' => '$2,800'],['name' => 'Hosting + Dominio .pe', 'status' =>'pending', 'due' => '2026-08-01', 'price' => '$120/año']],
             'br' => [['name' => 'Verde Orgánico', 'hex' =>'#2E7D32'],['name' => 'Crema', 'hex' =>'#FFF8E1'],['name' => 'Marrón Tierra', 'hex' =>'#5D4037']]]
        ]]
    ];

    $ic = $db->prepare("INSERT INTO clients (company, email, ruc, since, verified) VALUES (?,?,?,?,true) RETURNING id");
    $ip = $db->prepare("INSERT INTO projects (client_id, name, progress, phase) VALUES (?,?,?,?) RETURNING id");
    $im = $db->prepare("INSERT INTO milestones (project_id, title, status, position) VALUES (?,?,?,?)");
    $is = $db->prepare("INSERT INTO services (project_id, name, status, due_date, price) VALUES (?,?,?,?,?)");
    $ib = $db->prepare("INSERT INTO brand_resources (project_id, palette) VALUES (?,?)");

    foreach ($clients as $c) {
        $ic->execute([$c['company'], $c['email'], $c['ruc'], $c['since']]);
        $cid = $ic->fetchColumn();
        foreach ($c['projects'] as $p) {
            $ip->execute([$cid, $p['name'], $p['progress'], $p['phase']]);
            $pid = $ip->fetchColumn();
            foreach ($p['ms'] as $m) $im->execute([$pid, $m['title'], $m['status'], $m['pos']]);
            foreach ($p['sv'] as $s) $is->execute([$pid, $s['name'], $s['status'], $s['due'] ?? null, $s['price']]);
            $ib->execute([$pid, json_encode($p['br'])]);
        }
    }
}
