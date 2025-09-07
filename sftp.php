 <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<h2><p><a href="https://github.com/Omax64MXG4ming">--Created By Omax--</a></p></h2>


<?php
/**
 * 
 * Editor/Administrador de archivos (simple) — protegido por contraseña en config/connection.php
 *
 * Requisitos:
 *  - PHP 7+
 *  - session_start()
 *  - ZipArchive (opcional para extraer zip)
 *
 * Seguridad:
 *  - Restringido a __DIR__ (no permite salir de la carpeta)
 *  - Protege de editar/eliminar el propio archivo del panel
 *  - Token CSRF para formularios
 */

// ---------------- CONFIG / LOGIN ----------------
session_start();
/*You can change the address of your password. (must be .php) password: any, a place where it is less accessible (It should not be before the main folder, but the one where they are like this. normal)*/
// Cargar contraseña
$cfg = __DIR__ . '/config/connection.php';
if (!is_file($cfg)) {
    http_response_code(500);
    die("Falta config/connection.php con \$password definido.");
}
require $cfg;
if (!isset($password)) {
    http_response_code(500);
    die("En config/connection.php debe existir \$password = '...';");
}

// Nombre propio del script para protegerlo
$self_basename = basename(__FILE__);

// ---------------- HELPERS ----------------
function real_join($base, $rel) {
    // evita .. y NUL
    $rel = str_replace(["\0"], "", $rel);
    $rel = preg_replace('#(^[\\/]+)|([\\/]+$)#', '', $rel);
    $path = $rel === '' ? $base : $base . DIRECTORY_SEPARATOR . $rel;
    $real = realpath($path);
    if ($real === false) {
        // si no existe (p.ej para crear), normalizamos manualmente
        $realParent = realpath(dirname($path));
        if ($realParent === false) return null;
        $real = $realParent . DIRECTORY_SEPARATOR . basename($path);
    }
    // verificar dentro de base
    $baseN = rtrim(str_replace('\\','/',$base),'/').'/';
    $realN = rtrim(str_replace('\\','/',$real),'/').'/';
    return (strpos($realN, $baseN) === 0) ? $real : null;
}
function sanitize_filename($name) {
    $name = trim($name);
    // quitar caracteres peligrosos para nombres
    $name = preg_replace('/[\/\\\\:\*\?"<>\|\x00-\x1F]/u', '_', $name);
    return $name;
}
function rrmdir($dir) {
    if (!is_dir($dir)) return @unlink($dir);
    $items = scandir($dir);
    if ($items === false) return false;
    foreach ($items as $it) {
        if ($it==='.'||$it==='..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($p)) { if (!rrmdir($p)) return false; }
        else { if (!@unlink($p)) return false; }
    }
    return @rmdir($dir);
}
function rcopy($src, $dst) {
    if (is_dir($src)) {
        if (!file_exists($dst) && !@mkdir($dst, 0775)) return false;
        $items = scandir($src);
        if ($items === false) return false;
        foreach ($items as $it) {
            if ($it==='.'||$it==='..') continue;
            if (!rcopy($src.DIRECTORY_SEPARATOR.$it, $dst.DIRECTORY_SEPARATOR.$it)) return false;
        }
        return true;
    } else {
        return @copy($src, $dst);
    }
}
function is_text_file($name) {
    $t = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $txt = ['php','txt','html','htm','css','js','json','sql','md','xml','ini','cfg','conf'];
    return in_array($t, $txt, true);
}
function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ---------------- AUTENTICACIÓN ----------------
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
$errors = [];
$notices = [];
if (!isset($_SESSION['auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pass'])) {
        if (hash_equals((string)$password, (string)$_POST['login_pass'])) {
            $_SESSION['auth'] = true;
            // inicializar portapapeles (clipboard)
            $_SESSION['clipboard'] = null;
            $_SESSION['token'] = bin2hex(random_bytes(16));
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $errors[] = "Contraseña incorrecta.";
        }
    }
    // render login sencillo
    ?>
    <!doctype html>
    <html lang="es">

<head><meta charset="UTF-8"></head>

<body>

<p><span id="horaLocal"></span></p>

<script>

function actualizarHora() {

  const ahora = new Date();

  document.getElementById('horaLocal').textContent = ahora.toLocaleTimeString();

}

actualizarHora();

setInterval(actualizarHora, 1000);

</script>

</body>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Login — Editor FTP</title>
        <style>
            body{font-family:system-ui,Segoe UI,Roboto,Arial;background:linear-gradient(160deg,#081226,#07132a);color:#e6eef6;display:grid;place-items:center;height:100vh;margin:0}
            .card{background:rgba(255,255,255,0.03);padding:24px;border-radius:12px;box-shadow:0 6px 30px rgba(2,6,23,0.6);width:340px}
            h1{margin:0 0 10px;font-size:20px}
            input{width:100%;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,0.06);background:rgba(0,0,0,0.25);color:#e6eef6;margin-bottom:10px}
            button{width:100%;padding:10px;border-radius:10px;border:0;background:#06b6d4;color:#012;font-weight:700}
            .note{font-size:13px;color:#9fb0c9;margin-top:8px}
            .err{background:#4f1720;color:#ffd6d6;padding:8px;border-radius:8px;margin-bottom:10px}
        </style>
    </head>
    <body>
        <form class="card" method="post" autocomplete="off">
            <h1>Editor FTP — Acceso</h1>
            <p class="note">Introduce la contraseña definida</p>
            <?php if ($errors): ?><div class="err"><?= esc(implode(' | ',$errors)) ?></div><?php endif; ?>
            <input type="password" name="login_pass" placeholder="Contraseña" required autofocus>
            <button type="submit">Entrar🔓</button>
            <div class="note">Salida:<a href="./" style="color:#9feafc">Cerrar 🚪</a></div>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// ---------------- RUTA BASE Y ACTUAL ----------------
$BASE_DIR = realpath(__DIR__); // carpeta segura
if ($BASE_DIR === false) { die("Error: ruta base inválida."); }

$relPath = $_GET['path'] ?? '';
$relPath = trim($relPath, "/\\");
$absPath = real_join($BASE_DIR, $relPath);
if ($absPath === null || !is_dir($absPath)) {
    $absPath = $BASE_DIR;
    $relPath = '';
}

// CSRF token
if (!isset($_SESSION['token'])) $_SESSION['token'] = bin2hex(random_bytes(16));
$token = $_SESSION['token'];

// ---------------- ACCIONES ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validar token
    $post_token = $_POST['token'] ?? '';
    if (!hash_equals($token, $post_token)) {
        $errors[] = "Token inválido. Recarga la página y vuelve a intentar.";
    } else {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'mkdir':
                $name = sanitize_filename($_POST['name'] ?? '');
                if ($name === '') $errors[] = "Nombre vacío.";
                else {
                    $dst = real_join($absPath, $name);
                    if ($dst === null) $errors[] = "Ruta inválida.";
                    else {
                        if (!file_exists($dst)) {
                            if (@mkdir($dst, 0775)) $notices[] = "Carpeta creada: $name";
                            else $errors[] = "No se pudo crear carpeta.";
                        } else $errors[] = "Ya existe.";
                    }
                }
                break;

            case 'create_file':
                $name = sanitize_filename($_POST['name'] ?? '');
                if ($name === '') $errors[] = "Nombre vacío.";
                else {
                    if ($name === $self_basename) { $errors[] = "No puedes crear o sobrescribir el archivo del panel."; break; }
                    $dst = real_join($absPath, $name);
                    if ($dst === null) $errors[] = "Ruta inválida.";
                    else {
                        if (file_put_contents($dst, "") !== false) $notices[] = "Archivo creado: $name";
                        else $errors[] = "No se pudo crear el archivo.";
                    }
                }
                break;

            case 'upload':
                if (!isset($_FILES['files'])) { $errors[] = "No hay archivos."; break; }
                foreach ($_FILES['files']['name'] as $i => $orig) {
                    $name = sanitize_filename($orig);
                    if ($name === '') { $errors[] = "Nombre inválido."; continue; }
                    if ($name === $self_basename) { $errors[] = "No se permite subir el archivo del panel."; continue; }
                    $tmp = $_FILES['files']['tmp_name'][$i];
                    $err = $_FILES['files']['error'][$i];
                    if ($err !== UPLOAD_ERR_OK) { $errors[] = "Error subiendo $orig."; continue; }
                    $dst = real_join($absPath, $name);
                    if ($dst === null) { $errors[] = "Ruta inválida para $name"; continue; }
                    if (!@move_uploaded_file($tmp, $dst)) $errors[] = "No se pudo guardar $name";
                    else $notices[] = "Subido: $name";
                }
                break;

            case 'delete':
                $items = (array)($_POST['items'] ?? []);
                if (empty($items)) { $errors[] = "Ningún elemento seleccionado."; break; }
                $deleted = 0;
                foreach ($items as $rel) {
                    $rel = trim($rel, "/\\");
                    $p = real_join($absPath, $rel);
                    if ($p === null) { $errors[] = "Ruta inválida: $rel"; continue; }
                    if (basename($p) === $self_basename) { $errors[] = "Operación no permitida sobre el panel: $rel"; continue; }
                    if (!file_exists($p)) { $errors[] = "No existe: $rel"; continue; }
                    if (is_dir($p)) {
                        if (!rrmdir($p)) $errors[] = "No se pudo eliminar carpeta: $rel";
                        else { $deleted++; }
                    } else {
                        if (!@unlink($p)) $errors[] = "No se pudo eliminar archivo: $rel";
                        else { $deleted++; }
                    }
                }
                if ($deleted) $notices[] = "Eliminados: $deleted elemento(s).";
                break;

            case 'rename':
                $old = trim($_POST['old'] ?? '');
                $new = sanitize_filename($_POST['new'] ?? '');
                if ($old === '' || $new === '') { $errors[] = "Datos inválidos."; break; }
                $pOld = real_join($absPath, $old);
                $pNew = real_join($absPath, $new);
                if ($pOld === null || $pNew === null) { $errors[] = "Ruta inválida."; break; }
                if (basename($pOld) === $self_basename || basename($pNew) === $self_basename) { $errors[] = "No se puede renombrar al archivo del panel."; break; }
                if (!@rename($pOld, $pNew)) $errors[] = "No se pudo renombrar.";
                else $notices[] = "Renombrado.";
                break;

            case 'copy_cut':
                $mode = ($_POST['mode'] ?? 'copy') === 'cut' ? 'cut' : 'copy';
                $items = (array)($_POST['items'] ?? []);
                $abs_items = [];
                foreach ($items as $r) {
                    $r = trim($r, "/\\");
                    $p = real_join($absPath, $r);
                    if ($p !== null && file_exists($p)) $abs_items[] = $p;
                }
                if (empty($abs_items)) $errors[] = "No hay items válidos.";
                else {
                    $_SESSION['clipboard'] = ['mode'=>$mode, 'items'=>$abs_items];
                    $notices[] = "Portapapeles: " . count($abs_items) . " item(s) ($mode).";
                }
                break;

            case 'paste':
                $clip = $_SESSION['clipboard'] ?? null;
                if (empty($clip['items'])) { $errors[] = "Portapapeles vacío."; break; }
                $mode = $clip['mode'] ?? 'copy';
                $moved = 0;
                foreach ($clip['items'] as $src) {
                    // validar que src está dentro de base
                    if (strpos(str_replace('\\','/',$src), str_replace('\\','/',$BASE_DIR)) !== 0) { $errors[] = "Item fuera de base: $src"; continue; }
                    if (!file_exists($src)) { $errors[] = "No existe: $src"; continue; }
                    $name = basename($src);
                    if ($name === $self_basename) { $errors[] = "Operación no permitida sobre el panel: $name"; continue; }
                    $dst = real_join($absPath, $name);
                    if ($dst === null) { $errors[] = "Destino inválido para $name"; continue; }
                    if ($mode === 'copy') {
                        if (is_dir($src)) {
                            if (!rcopy($src, $dst)) { $errors[] = "No copiado: $name"; continue; }
                        } else {
                            if (!@copy($src, $dst)) { $errors[] = "No copiado: $name"; continue; }
                        }
                    } else { // cut -> move
                        if (!@rename($src, $dst)) { $errors[] = "No movido: $name"; continue; }
                    }
                    $moved++;
                }
                if ($moved) $notices[] = ($mode==='copy'?'Copiados: ':'Movidos: ') . $moved;
                if ($mode==='cut') $_SESSION['clipboard'] = null;
                break;

            case 'extract':
                $zipRel = trim($_POST['zip'] ?? '');
                if ($zipRel === '') { $errors[] = "Archivo zip no especificado."; break; }
                $zipAbs = real_join($absPath, $zipRel);
                if ($zipAbs === null || !is_file($zipAbs)) { $errors[] = "ZIP inválido."; break; }
                if (!class_exists('ZipArchive')) { $errors[] = "ZipArchive no disponible en el servidor."; break; }
                $zip = new ZipArchive();
                if ($zip->open($zipAbs) === true) {
                    if ($zip->extractTo($absPath)) $notices[] = "ZIP extraído.";
                    else $errors[] = "Error al extraer ZIP.";
                    $zip->close();
                } else $errors[] = "No se pudo abrir ZIP.";
                break;

            case 'save_edit':
                $fileRel = trim($_POST['file'] ?? '');
                $content = $_POST['content'] ?? '';
                $fileAbs = real_join($absPath, $fileRel);
                if ($fileAbs === null || !is_file($fileAbs)) { $errors[] = "Archivo inválido."; break; }
                if (basename($fileAbs) === $self_basename) { $errors[] = "No se puede editar el archivo del panel."; break; }
                if (file_put_contents($fileAbs, $content) === false) $errors[] = "No se pudo guardar.";
                else $notices[] = "Archivo guardado.";
                break;

            default:
                $errors[] = "Acción desconocida.";
        }
    }
    // renovar token y recargar
    $_SESSION['token'] = bin2hex(random_bytes(16));
    header("Location: " . $_SERVER['PHP_SELF'] . ( $relPath ? '?path=' . rawurlencode($relPath) : '' ));
    exit;
}

// ---------------- LISTADO ACTUAL ----------------
$entries = scandir($absPath) ?: [];
$dirs = $files = [];
foreach ($entries as $e) {
    if ($e === '.' || $e === '..') continue;
    if ($e === $self_basename) continue; // ocultar propio archivo
    $full = $absPath . DIRECTORY_SEPARATOR . $e;
    if (is_dir($full)) $dirs[] = $e;
    else $files[] = $e;
}

// obtener información de portapapeles
$clipboard = $_SESSION['clipboard'] ?? null;

// ---------------- RENDER HTML ----------------
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Editor FTP</title>
<style>
:root{--bg:#071227;--panel:#0e1726;--muted:#90a4b7;--accent:#06b6d4;--card:#0b1320}
*{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:linear-gradient(180deg,#071227,#081827);color:#e6eef6}
header{display:flex;gap:10px;align-items:center;padding:12px 16px;background:var(--panel);border-bottom:1px solid rgba(255,255,255,0.03)}
h1{margin:0;font-size:18px}
.path{font-family:ui-monospace,Consolas;padding:6px 10px;background:#061020;border-radius:8px}
.actions{margin-left:auto;display:flex;gap:8px}
.btn{padding:8px 12px;border-radius:10px;border:1px solid rgba(255,255,255,0.03);background:transparent;color:var(--muted);cursor:pointer}
.btn.primary{background:var(--accent);color:#012;font-weight:700}
.container{padding:16px;display:grid;grid-template-columns:360px 1fr;gap:16px}
.sidebar{background:var(--card);padding:12px;border-radius:12px;min-height:400px}
.main{background:rgba(0,0,0,0.04);padding:12px;border-radius:12px;min-height:400px}
.form-row{display:flex;gap:8px;margin-bottom:8px}
input[type=text],input[type=file],select,textarea{width:100%;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:#051225;color:#e6eef6}
table{width:100%;border-collapse:collapse}
th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.02);font-size:14px}
.row-actions form{display:inline;margin-right:6px}
.msg{padding:8px;border-radius:8px;margin-bottom:8px}
.msg.err{background:#3f1a1f;color:#ffd6d6}
.msg.ok{background:#0b3526;color:#c7f1dd}
.small{font-size:12px;color:var(--muted)}
.footer{margin-top:12px;font-size:12px;color:var(--muted)}
.pill{background:#071a28;padding:6px 8px;border-radius:999px;font-size:12px;color:var(--muted)}
.clip{font-size:13px;color:var(--muted)}
.preview{white-space:pre-wrap;background:#041423;padding:8px;border-radius:8px;max-height:220px;overflow:auto}
</style>
<script>
function confirmDelete(){ return confirm("¿Eliminar los elementos seleccionados? Esta acción es irreversible."); }
function confirmExtract(){ return confirm("¿Extraer este ZIP aquí?"); }
function toggleEdit(id){ document.getElementById(id).classList.toggle('hidden'); }
</script>
</head>
<body>
<header>
    <h1>Editor FTP</h1>
    <div class="path">/<?= esc($relPath ?: '.') ?></div>
    <div class="actions">
        <a class="btn" href="<?= $_SERVER['PHP_SELF'] ?>">Raíz</a>
        <a class="btn" href="<?= $_SERVER['PHP_SELF'] ?>?logout=1">Salir</a>
    </div>
</header>

<div class="container">
    <aside class="sidebar">
        <div class="small">Acciones rápidas</div>

        <!-- Crear carpeta -->
        <form method="post" class="form-row">
            <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
            <input type="hidden" name="action" value="mkdir">
            <input type="text" name="name" placeholder="Nueva carpeta" required>
            <button class="btn primary" type="submit">Crear</button>
        </form>

        <!-- Crear archivo -->
        <form method="post" class="form-row">
            <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
            <input type="hidden" name="action" value="create_file">
            <input type="text" name="name" placeholder="nuevo.txt" required>
            <button class="btn primary" type="submit">Archivo</button>
        </form>

        <!-- Subir archivos -->
        <form method="post" enctype="multipart/form-data" class="form-row">
            <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="files[]" multiple required>
            <button class="btn" type="submit">Subir</button>
        </form>

        <div class="footer small">
            Portapapeles: <span class="pill"><?= esc($clipboard['mode'] ?? '-') ?></span><br>
            Items: <span class="clip"><?= esc(implode(', ', array_map('basename', $clipboard['items'] ?? []))) ?></span>
        </div>

        <?php if ($errors): foreach ($errors as $e): ?>
            <div class="msg err"><?= esc($e) ?></div>
        <?php endforeach; endif; ?>

        <?php if ($notices): foreach ($notices as $n): ?>
            <div class="msg ok"><?= esc($n) ?></div>
        <?php endforeach; endif; ?>

    </aside>

    <section class="main">
        <div style="margin-bottom:12px;">
            <form method="get" style="display:inline">
                <input type="hidden" name="path" value="<?= esc($relPath) ?>">
                <button class="btn">Recargar</button>
            </form>
            <?php if ($relPath !== ''): 
                $parent = dirname($relPath);
                if ($parent === '.' || $parent === DIRECTORY_SEPARATOR) $parent = '';
            ?>
                <a class="btn" href="?path=<?= rawurlencode($parent) ?>">Subir</a>
            <?php endif; ?>
        </div>

        <h3>Carpetas</h3>
        <?php if (empty($dirs)): ?><div class="small">No hay carpetas.</div><?php else: ?>
            <table>
                <thead><tr><th>Nombre</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($dirs as $d): 
                    $to = ($relPath === '') ? $d : $relPath . '/' . $d;
                ?>
                    <tr>
                        <td>📁 <a href="?path=<?= rawurlencode($to) ?>"><?= esc($d) ?></a></td>
                        <td class="row-actions">
                            <form method="post" style="display:inline">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="copy_cut">
                                <input type="hidden" name="mode" value="copy">
                                <input type="hidden" name="items[]" value="<?= esc($d) ?>">
                                <button class="btn">Copiar</button>
                            </form>

                            <form method="post" style="display:inline">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="copy_cut">
                                <input type="hidden" name="mode" value="cut">
                                <input type="hidden" name="items[]" value="<?= esc($d) ?>">
                                <button class="btn">Cortar</button>
                            </form>

                            <form method="post" style="display:inline" onsubmit="return confirm('¿Borrar carpeta <?= esc($d) ?>?');">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="items[]" value="<?= esc($d) ?>">
                                <button class="btn">Borrar</button>
                            </form>

                            <form method="post" style="display:inline">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="old" value="<?= esc($d) ?>">
                                <input type="text" name="new" placeholder="Nuevo nombre" required style="width:140px;display:inline-block">
                                <button class="btn">Renombrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 style="margin-top:14px">Archivos</h3>
        <?php if (empty($files)): ?><div class="small">No hay archivos.</div><?php else: ?>
            <table>
                <thead><tr><th>Nombre</th><th>Tamaño</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($files as $f):
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    $isText = is_text_file($f);
                ?>
                    <tr>
                        <td>📄 <?= esc($f) ?></td>
                        <td class="small"><?= @filesize($absPath . DIRECTORY_SEPARATOR . $f) ?: '-' ?> bytes</td>
                        <td class="row-actions">
                            <!-- Download -->
                            <form method="post" style="display:inline">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="download_stub">
                                <input type="hidden" name="file" value="<?= esc($f) ?>">
                                <button class="btn" onclick="location.href='?dl=<?= rawurlencode($relPath? $relPath.'/'.$f : $f) ?>'">Descargar</button>
                            </form>

                            <!-- Copy -->
                            <form method="post" style="display:inline">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="copy_cut">
                                <input type="hidden" name="mode" value="copy">
                                <input type="hidden" name="items[]" value="<?= esc($f) ?>">
                                <button class="btn">Copiar</button>
                            </form>

                            <!-- Cut -->
                            <form method="post" style="display:inline">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="copy_cut">
                                <input type="hidden" name="mode" value="cut">
                                <input type="hidden" name="items[]" value="<?= esc($f) ?>">
                                <button class="btn">Cortar</button>
                            </form>

                            <!-- Delete -->
                            <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar <?= esc($f) ?>?');">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="items[]" value="<?= esc($f) ?>">
                                <button class="btn">Borrar</button>
                            </form>

                            <!-- Rename -->
                            <form method="post" style="display:inline">
                                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="old" value="<?= esc($f) ?>">
                                <input type="text" name="new" placeholder="Nuevo nombre" required style="width:140px;display:inline-block">
                                <button class="btn">Renombrar</button>
                            </form>

                            <?php if ($ext === 'zip'): ?>
                                <form method="post" style="display:inline" onsubmit="return confirmExtract();">
                                    <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                                    <input type="hidden" name="action" value="extract">
                                    <input type="hidden" name="zip" value="<?= esc($f) ?>">
                                    <button class="btn">Extraer</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($isText): ?>
                                <button class="btn" onclick="location.href='?edit=<?= rawurlencode($relPath? $relPath.'/'.$f : $f) ?>'">Editar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div style="margin-top:12px">
            <form method="post" style="display:inline">
                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                <input type="hidden" name="action" value="paste">
                <button class="btn">Pegar</button>
            </form>

            <form method="post" onsubmit="return confirmDelete();" style="display:inline">
                <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                <input type="hidden" name="action" value="delete">
                <!-- para permitir seleccionar manualmente, puedes listar checkboxes; por simplicidad aquí borrado por forms individuales -->
                <button class="btn">Borrar seleccionados</button>
            </form>
        </div>

        <?php
        // --------- EDITOR SIMPLE ----------
        if (isset($_GET['edit'])):
            $editRel = $_GET['edit'];
            $editRel = trim($editRel, "/\\");
            $editAbs = real_join($absPath, $editRel);
            if ($editAbs === null || !is_file($editAbs)) {
                echo "<div class='msg err'>Archivo inválido para editar.</div>";
            } elseif (basename($editAbs) === $self_basename) {
                echo "<div class='msg err'>No se puede editar el archivo del panel.</div>";
            } else {
                $content = file_get_contents($editAbs);
                ?>
                <h3>Editar: <?= esc($editRel) ?></h3>
                <form method="post">
                    <input type="hidden" name="token" value="<?= esc($_SESSION['token']) ?>">
                    <input type="hidden" name="action" value="save_edit">
                    <input type="hidden" name="file" value="<?= esc($editRel) ?>">
                    <textarea name="content" rows="18" style="width:100%;font-family:monospace;"><?= esc($content) ?></textarea>
                    <div style="margin-top:8px">
                        <button class="btn primary" type="submit">Guardar</button>
                        <button class="btn" type="button" onclick="location.href='<?= $_SERVER['PHP_SELF'] . ($relPath ? '?path='.rawurlencode($relPath) : '') ?>'">Cancelar</button>
                    </div>
                </form>
                <?php
            }
        endif;
        ?>

        <!-- Descarga directa (manejo GET) -->
        <?php if (isset($_GET['dl'])):
            $dl = trim($_GET['dl'], "/\\");
            $dlAbs = real_join($BASE_DIR, $dl);
            if ($dlAbs && is_file($dlAbs) && strpos($dlAbs, $BASE_DIR) === 0 && basename($dlAbs) !== $self_basename) {
                // envío forzado de descarga
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.basename($dlAbs).'"');
                header('Content-Length: ' . filesize($dlAbs));
                flush();
                readfile($dlAbs);
                exit;
            } else {
                echo "<div class='msg err'>Archivo no disponible para descarga.</div>";
            }
        endif; ?>

    </section>
</div>
</body>
</html><h2><p><a href="https://github.com/Omax64MXG4ming">--Created By Omax--</a></p></h2>

