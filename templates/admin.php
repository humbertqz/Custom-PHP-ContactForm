<?php

declare(strict_types=1);

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$utc = new DateTimeZone('UTC');
$metadataLabels = [
    'name' => 'Nombre', 'email' => 'Correo', 'phone' => 'Teléfono', 'form_id' => 'Formulario', 'source_url' => 'Origen',
];
$messageCount = count($messages);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $loggedIn ? 'Mensajes de contacto' : 'Iniciar sesión' ?></title>
    <style>
        :root {
            color-scheme: light;
            --canvas: #f5f7fa;
            --surface: #fff;
            --muted-surface: #f8fafc;
            --ink: #172136;
            --muted: #536278;
            --line: #dce3ed;
            --accent: #284d82;
            --accent-tint: #e9f0fa;
            font: 16px/1.5 system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--ink);
            background: var(--canvas);
        }

        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; }
        form, h1, h2, p, dl, dd { margin: 0; }
        :focus-visible { outline: 3px solid #3671b5; outline-offset: 3px; }

        .wrap { max-width: 72rem; margin-inline: auto; padding-inline: clamp(1rem, 4vw, 2.75rem); }
        .topbar { background: var(--surface); border-bottom: 1px solid var(--line); }
        .topbar-inner, .heading, .pagination, .pagination-links {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .topbar-inner { min-height: 4.5rem; padding-block: .65rem; }
        .brand { display: inline-flex; align-items: center; gap: .7rem; font-weight: 700; }
        .brand-icon, .empty-icon {
            display: grid;
            place-items: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: .65rem;
            background: var(--accent-tint);
            color: var(--accent);
        }

        .envelope {
            position: relative;
            display: block;
            width: 1.05rem;
            height: .76rem;
            overflow: hidden;
            border: 1.5px solid currentColor;
            border-radius: .17rem;
        }

        .envelope::after {
            position: absolute;
            top: -.31rem;
            left: .17rem;
            width: .55rem;
            height: .55rem;
            border: 1.5px solid currentColor;
            border-top: 0;
            border-left: 0;
            transform: rotate(45deg);
            content: '';
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            padding: .45rem .85rem;
            border: 1px solid var(--line);
            border-radius: .55rem;
            color: var(--ink);
            background: var(--surface);
            font: inherit;
            font-size: .875rem;
            font-weight: 650;
            text-decoration: none;
            cursor: pointer;
        }

        .button:hover { background: var(--muted-surface); border-color: #a9b9cd; }
        .primary { background: var(--accent); color: #fff; border-color: var(--accent); }
        .primary:hover { background: #1e3d68; border-color: #1e3d68; }
        main { padding-block: clamp(2rem, 5vw, 4rem); }
        .heading { margin-bottom: 1.5rem; align-items: flex-end; }
        h1 { font-size: clamp(1.85rem, 4vw, 2.75rem); line-height: 1.12; letter-spacing: -.045em; }
        h2 { font-size: .96rem; line-height: 1.35; }
        .muted { font-size: .875rem; color: var(--muted); }
        .heading .muted { margin-bottom: .15rem; }

        .inbox, .login {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: .9rem;
            box-shadow: 0 3px 12px rgb(31 51 78 / 4%);
        }

        .columns, .message-row {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 1.7fr) 7.75rem 1.75rem;
            gap: 1.15rem;
            align-items: center;
            padding-inline: clamp(.85rem, 2.5vw, 1.5rem);
        }

        .columns {
            padding-block: .75rem;
            border-radius: .85rem .85rem 0 0;
            background: var(--muted-surface);
            color: var(--muted);
            font-size: .8rem;
            font-weight: 650;
        }

        .entry { border-top: 1px solid var(--line); }
        .entry:last-child:not([open]) .message-row { border-radius: 0 0 .85rem .85rem; }
        .entry[open] .message-row, .message-row:hover { background: #f1f5fa; }
        .message-row { min-height: 5.25rem; padding-block: .9rem; list-style: none; cursor: pointer; }
        .message-row::-webkit-details-marker { display: none; }
        .message-row:focus-visible { outline-offset: -3px; }
        .sender { display: grid; grid-template-columns: 2.4rem minmax(0, 1fr); gap: .7rem; align-items: center; }
        .initials {
            display: grid;
            place-items: center;
            width: 2.4rem;
            height: 2.4rem;
            border-radius: .65rem;
            background: var(--accent-tint);
            color: var(--accent);
            font-size: .8rem;
            font-weight: 750;
        }

        .single-line { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sender-name, .subject { font-size: .915rem; font-weight: 675; }
        .received { font-variant-numeric: tabular-nums; font-size: .875rem; }
        .received .muted { display: block; font-size: .8rem; }
        .row-icon { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; color: var(--muted); }
        .row-icon::after {
            width: .5rem;
            height: .5rem;
            margin-top: -.2rem;
            border: solid currentColor;
            border-width: 0 1.5px 1.5px 0;
            transform: rotate(45deg);
            content: '';
        }

        .entry[open] .row-icon::after { margin-top: .2rem; transform: rotate(225deg); }
        .message-detail {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(12rem, 34%);
            gap: clamp(1.25rem, 4vw, 2.5rem);
            padding: clamp(1rem, 3vw, 1.75rem) clamp(.85rem, 2.5vw, 1.5rem);
            border-top: 1px solid var(--line);
            background: var(--muted-surface);
            overflow-wrap: anywhere;
        }

        .entry:last-child .message-detail { border-radius: 0 0 .85rem .85rem; }
        .message-body { white-space: pre-wrap; margin-top: .6rem; }
        .metadata { display: grid; gap: .6rem; margin-top: .6rem; }
        .metadata dt { color: var(--muted); font-size: .78rem; font-weight: 650; }
        .metadata dd { font-size: .875rem; }
        .extra-fields { margin-top: 1.1rem; padding-top: 1rem; border-top: 1px solid var(--line); }

        .empty { padding: clamp(2rem, 7vw, 4rem) 1rem; text-align: center; }
        .empty-icon { margin: 0 auto .8rem; }
        .pagination { margin-top: 1.15rem; }
        .pagination-links { justify-content: flex-start; }
        .error {
            margin-bottom: 1rem;
            padding: .75rem .9rem;
            border: 1px solid #f1c0bb;
            border-radius: .55rem;
            background: #fff1ef;
            color: #862831;
            font-size: .875rem;
        }

        .login { max-width: 26rem; margin: clamp(1rem, 8vh, 4rem) auto 0; padding: clamp(1.2rem, 5vw, 2rem); }
        .login h1 { font-size: 1.85rem; margin-bottom: 1.35rem; }
        .login label { display: block; margin: 1rem 0 .35rem; font-size: .875rem; font-weight: 650; }
        .login input {
            width: 100%;
            min-height: 2.75rem;
            padding: .6rem .7rem;
            border: 1px solid #a9b9cd;
            border-radius: .5rem;
            color: var(--ink);
            background: var(--surface);
            font: inherit;
        }

        .login .button { width: 100%; margin-top: 1.2rem; }

        @media (max-width: 42rem) {
            .columns { display: none; }
            .entry:first-of-type { border-top: 0; }
            .message-row {
                grid-template-columns: minmax(0, 1fr) 1.75rem;
                grid-template-areas: "sender control" "excerpt excerpt" "time time";
                gap: .55rem .5rem;
            }
            .sender { grid-area: sender; }
            .excerpt { grid-area: excerpt; padding-inline-start: 3.1rem; }
            .received { grid-area: time; padding-inline-start: 3.1rem; color: var(--muted); font-size: .78rem; }
            .received .muted { display: inline; margin-inline-start: .25rem; font-size: inherit; }
            .row-icon { grid-area: control; }
            .message-detail { grid-template-columns: minmax(0, 1fr); }
        }

        @media (max-width: 24rem) {
            .heading { align-items: flex-start; flex-direction: column; gap: .35rem; }
            .excerpt, .received { padding-inline-start: 0; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="wrap topbar-inner">
            <span class="brand">
                <span class="brand-icon" aria-hidden="true"><i class="envelope"></i></span> Contacto
            </span>
            <?php if ($loggedIn) : ?>
            <form method="post" action="<?= $escape($path) ?>">
                <input type="hidden" name="csrf" value="<?= $escape($token) ?>">
                <button class="button" name="action" value="logout">Cerrar sesión</button>
            </form>
            <?php endif; ?>
        </div>
    </header>
    <main class="wrap">
        <?php if ($loggedIn) : ?>
        <div class="heading">
            <h1>Mensajes de contacto</h1>
            <?php if ($messageCount) : ?>
            <p class="muted">
                <?= $messageCount === 1 ? '1 mensaje en esta página' : $messageCount . ' mensajes en esta página' ?>
            </p>
            <?php endif; ?>
        </div>
            <?php if ($error !== '') : ?>
        <p class="error" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>
        <div class="inbox">
            <?php if ($messages) : ?>
            <div class="columns" aria-hidden="true">
                <span>Remitente</span><span>Mensaje</span><span>Recibido</span>
            </div>
            <?php else : ?>
            <div class="empty">
                <span class="empty-icon" aria-hidden="true"><i class="envelope"></i></span>
                <h2><?= $page === 1 ? 'Aún no hay mensajes guardados.' : 'No hay mensajes en esta página.' ?></h2>
            </div>
            <?php endif; ?>
            <?php foreach ($messages as $entry) : ?>
                <?php
                $sender = $entry['name'] ?: $entry['email'] ?: 'Mensaje';
                $parts = preg_split('/\s+/u', trim($sender), -1, PREG_SPLIT_NO_EMPTY) ?: ['M'];
                $initials = mb_substr($parts[0], 0, 1, 'UTF-8');
                if (count($parts) > 1) {
                    $initials .= mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
                }
                $initials = mb_strtoupper($initials, 'UTF-8');
                $subject = $entry['subject'] ?: 'Sin asunto';
                $preview = preg_replace('/\s+/u', ' ', trim($entry['message'])) ?? '';
                $preview = mb_strimwidth($preview, 0, 140, '…', 'UTF-8');
                $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $entry['created_at'], $utc);
                $dateText = $date
                    ? $date->format('j') . ' ' . $months[(int) $date->format('n') - 1] . ' ' . $date->format('Y')
                    : $entry['created_at'];
                $extra = json_decode($entry['extra_fields'], true);
                ?>
            <details class="entry">
                <summary class="message-row">
                    <span class="sender">
                        <span class="initials" aria-hidden="true"><?= $escape($initials) ?></span>
                        <span>
                            <strong class="sender-name single-line"><?= $escape($sender) ?></strong>
                            <?php if ($entry['email'] !== '' && $entry['email'] !== $sender) : ?>
                            <span class="muted single-line"><?= $escape($entry['email']) ?></span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <span class="excerpt">
                        <span class="subject single-line"><?= $escape($subject) ?></span>
                        <?php if ($preview !== '') : ?>
                        <span class="muted single-line"><?= $escape($preview) ?></span>
                        <?php endif; ?>
                    </span>
                    <time class="received" datetime="<?= $escape($entry['created_at']) ?>">
                        <?= $escape($dateText) ?>
                        <?php if ($date) : ?>
                        <span class="muted"><?= $escape($date->format('H:i')) ?> UTC</span>
                        <?php endif; ?>
                    </time>
                    <span class="row-icon" aria-hidden="true"></span>
                </summary>
                <div class="message-detail">
                    <div>
                        <h2><?= $escape($entry['subject'] ?: 'Mensaje') ?></h2>
                        <p class="message-body"><?= $escape($entry['message']) ?></p>
                    </div>
                    <div>
                        <h2>Datos del envío</h2>
                        <dl class="metadata">
                            <?php foreach ($metadataLabels as $key => $label) : ?>
                                <?php if ($entry[$key] !== '') : ?>
                            <div><dt><?= $label ?></dt><dd><?= $escape($entry[$key]) ?></dd></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </dl>
                        <?php if (is_array($extra) && $extra !== []) : ?>
                        <div class="extra-fields">
                            <h2>Campos adicionales</h2>
                            <dl class="metadata">
                                <?php foreach ($extra as $key => $value) : ?>
                                    <?php
                                    $label = $key === 'utm' ? 'UTM' : str_replace('_', ' ', (string) $key);
                                    $text = is_string($value)
                                        ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
                                    ?>
                                <div><dt><?= $escape($label) ?></dt><dd><?= $escape($text) ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
            <?php if ($page > 1 || $hasMore) : ?>
        <nav class="pagination" aria-label="Páginas de mensajes">
            <span class="muted" aria-current="page">Página <?= $page ?></span>
            <div class="pagination-links">
                <?php if ($page > 1) : ?>
                <a class="button" href="<?= $escape($path) ?>?page=<?= $page - 1 ?>">Más recientes</a>
                <?php endif; ?>
                <?php if ($hasMore) : ?>
                <a class="button" href="<?= $escape($path) ?>?page=<?= $page + 1 ?>">Más antiguos</a>
                <?php endif; ?>
            </div>
        </nav>
            <?php endif; ?>
        <?php else : ?>
        <section class="login">
            <h1>Iniciar sesión</h1>
            <?php if ($error !== '') : ?>
            <p class="error" role="alert"><?= $escape($error) ?></p>
            <?php endif; ?>
            <form method="post" action="<?= $escape($path) ?>">
                <input type="hidden" name="csrf" value="<?= $escape($token) ?>">
                <label for="username">Usuario</label>
                <input id="username" name="username" autocomplete="username" maxlength="64" required>
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="button primary" name="action" value="login">Entrar</button>
            </form>
        </section>
        <?php endif; ?>
    </main>
</body>
</html>
