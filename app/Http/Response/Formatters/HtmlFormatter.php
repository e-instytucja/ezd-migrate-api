<?php

declare(strict_types=1);

namespace App\Http\Response\Formatters;

use App\Http\Response\Dto\ApiResponse;
use Illuminate\Http\Response;

final class HtmlFormatter extends AbstractFormatter
{
    // ── Entry point ───────────────────────────────────────────────────────────

    public function format(ApiResponse $response): Response
    {
        $payload = $this->normalize($response);
        $html    = $this->buildPage($payload, $response->statusCode, $response->success);

        return $this->buildResponse($html, $response->statusCode, $this->mimeType());
    }

    public function mimeType(): string
    {
        return 'text/html';
    }

    // ── Page skeleton ─────────────────────────────────────────────────────────

    private function buildPage(array $payload, int $code, bool $ok): string
    {
        $data      = $payload['data'] ?? null;
        $message   = isset($payload['message']) ? $this->esc($payload['message']) : null;
        $errorCode = isset($payload['error'])   ? $this->esc($payload['error'])   : null;

        // Try to find a meaningful page title in the data
        $pageTitle = 'API ' . ($ok ? 'Response' : 'Error') . ' — ' . $code;
        if (is_array($data)) {
            foreach (['znak', 'tytul', 'title', 'name', 'id'] as $k) {
                if (isset($data[$k]) && is_scalar($data[$k]) && $data[$k] !== '') {
                    $pageTitle = $this->esc((string) $data[$k]);
                    break;
                }
            }
        }

        $hdrClass  = $ok ? 'hdr--ok' : 'hdr--err';
        $statusLabel = $ok ? 'OK' : 'Error';

        $msgHtml = '';
        if ($message !== null) {
            $errPrefix = $errorCode ? '<code class="err-code">' . $errorCode . '</code> ' : '';
            $msgHtml = '<div class="msg">' . $errPrefix . $message . '</div>';
        }

        $dataHtml = $data !== null
            ? $this->renderAny($data, 'root')
            : '<p class="empty">Brak danych</p>';

        $css = $this->css();
        $js  = $this->js();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pl">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <title>{$pageTitle}</title>
          <style>{$css}</style>
        </head>
        <body>
          <header class="hdr {$hdrClass}">
            <span class="hdr__label">{$pageTitle}</span>
            <span class="hdr__badge">HTTP {$code} {$statusLabel}</span>
          </header>
          <main class="wrap">
            {$msgHtml}
            {$dataHtml}
          </main>
          <script>{$js}</script>
        </body>
        </html>
        HTML;
    }

    // ── Recursive renderer ────────────────────────────────────────────────────

    private function renderAny(mixed $val, string $key = ''): string
    {
        if (is_null($val)) {
            return '<span class="null">—</span>';
        }

        if (is_bool($val)) {
            return $val
                ? '<span class="bool-t">tak</span>'
                : '<span class="bool-f">nie</span>';
        }

        if (is_scalar($val)) {
            return '<span class="scalar">' . $this->esc((string) $val) . '</span>';
        }

        if (!is_array($val)) {
            return '';
        }

        // Indexed array of associative arrays → data table
        if ($this->isTable($val)) {
            return $this->renderTable($val);
        }

        // Indexed array of scalars → tag list
        if (array_is_list($val)) {
            return $this->renderList($val);
        }

        // Associative array → key-value sections
        return $this->renderKv($val, $key);
    }

    // ── Key-value section ─────────────────────────────────────────────────────

    private function renderKv(array $obj, string $parentKey = ''): string
    {
        $scalars = array_filter($obj, fn($v) => is_null($v) || is_bool($v) || is_scalar($v));
        $complex = array_filter($obj, fn($v) => is_array($v));

        $html = '';

        if (!empty($scalars)) {
            $html .= '<dl class="kv">';
            foreach ($scalars as $k => $v) {
                $label = $this->label((string) $k);
                $value = $this->renderAny($v, (string) $k);
                $html .= '<dt>' . $this->esc($label) . '</dt><dd>' . $value . '</dd>';
            }
            $html .= '</dl>';
        }

        foreach ($complex as $k => $v) {
            $label  = $this->label((string) $k);
            $inner  = $this->renderAny($v, (string) $k);
            $id     = 'sec-' . $this->esc((string) $k) . '-' . substr(md5($parentKey . $k), 0, 6);
            $count  = is_array($v) ? ' <span class="sec__count">(' . count($v) . ')</span>' : '';
            $html  .= <<<SECTION
            <section class="sec" id="{$id}">
              <button class="sec__hdr" onclick="tog('{$id}')" aria-expanded="true">
                <svg class="sec__chev" viewBox="0 0 16 16"><path d="M4 6l4 4 4-4"/></svg>
                <span class="sec__title">{$label}{$count}</span>
              </button>
              <div class="sec__body">{$inner}</div>
            </section>
            SECTION;
        }

        return $html ?: '<p class="empty">—</p>';
    }

    // ── Data table ────────────────────────────────────────────────────────────

    private function renderTable(array $list): string
    {
        // Collect all unique keys to form consistent columns
        $keys = [];
        foreach ($list as $row) {
            if (is_array($row)) {
                foreach (array_keys($row) as $k) {
                    $keys[$k] = true;
                }
            }
        }

        if (empty($keys)) {
            return '<p class="empty">—</p>';
        }

        $thead = '<tr>';
        foreach (array_keys($keys) as $k) {
            $thead .= '<th>' . $this->esc($this->label((string) $k)) . '</th>';
        }
        $thead .= '</tr>';

        $tbody = '';
        foreach ($list as $row) {
            $tbody .= '<tr>';
            foreach (array_keys($keys) as $k) {
                $cell   = is_array($row) ? ($row[$k] ?? null) : null;
                $tbody .= '<td>' . $this->renderCell($cell) . '</td>';
            }
            $tbody .= '</tr>';
        }

        return '<div class="tbl-wrap"><table class="tbl"><thead>' . $thead . '</thead><tbody>' . $tbody . '</tbody></table></div>';
    }

    // ── Simple list ───────────────────────────────────────────────────────────

    private function renderList(array $arr): string
    {
        if (empty($arr)) {
            return '<p class="empty">—</p>';
        }

        $items = implode('', array_map(
            fn($v) => '<li>' . $this->renderCell($v) . '</li>',
            $arr,
        ));

        return '<ul class="list">' . $items . '</ul>';
    }

    // ── Table cell renderer ───────────────────────────────────────────────────

    private function renderCell(mixed $val): string
    {
        if (is_null($val)) {
            return '<span class="null">—</span>';
        }

        if (is_bool($val)) {
            return $val ? '<span class="bool-t">tak</span>' : '<span class="bool-f">nie</span>';
        }

        if (is_scalar($val)) {
            $str = (string) $val;
            $display = $this->esc($this->truncate($str, 120));
            $full    = $this->esc($str);
            return '<span title="' . $full . '">' . $display . '</span>';
        }

        if (is_array($val)) {
            if (!array_is_list($val)) {
                // Inline compact view of nested object: first 3 scalar fields
                $parts = [];
                foreach ($val as $k => $v) {
                    if (is_scalar($v) && $v !== null && $v !== '') {
                        $parts[] = '<b>' . $this->esc($this->label((string) $k)) . ':</b> ' . $this->esc($this->truncate((string) $v, 40));
                    }
                    if (count($parts) >= 3) {
                        break;
                    }
                }
                return implode('<br>', $parts);
            }

            return '<span class="count">' . count($val) . ' ' . (count($val) === 1 ? 'element' : 'elementów') . '</span>';
        }

        return '';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns true when $arr is an indexed list where every item
     * is itself an associative (non-list) array.
     */
    private function isTable(array $arr): bool
    {
        if (!array_is_list($arr) || empty($arr)) {
            return false;
        }

        foreach ($arr as $item) {
            if (!is_array($item) || array_is_list($item)) {
                return false;
            }
        }

        return true;
    }

    private function label(string $key): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $key));
    }

    private function truncate(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '…' : $s;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ── Embedded CSS ──────────────────────────────────────────────────────────

    private function css(): string
    {
        return <<<'CSS'
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #f4f6f9;
            --surface: #ffffff;
            --border:  #e2e8f0;
            --text:    #0f172a;
            --muted:   #64748b;
            --accent:  #1d4ed8;
            --ok:      #14532d;
            --err:     #7f1d1d;
            --null:    #94a3b8;
            --bool-t:  #166534;
            --bool-f:  #9a3412;
            --r:       7px;
            --font:    -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            --mono:    'Cascadia Code', 'Fira Code', Consolas, monospace;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.55;
        }

        /* ── Header ── */
        .hdr {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .hdr--ok  { background: var(--ok);  color: #fff; }
        .hdr--err { background: var(--err); color: #fff; }
        .hdr__label { font-weight: 600; font-size: 15px; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .hdr__badge {
            background: rgba(255,255,255,.18);
            padding: 2px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            white-space: nowrap;
        }

        /* ── Page layout ── */
        .wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px 16px 60px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* ── Message / error callout ── */
        .msg {
            background: #fefce8;
            border: 1px solid #ca8a04;
            border-radius: var(--r);
            padding: 10px 14px;
            color: #713f12;
            font-size: 13px;
        }
        .err-code {
            font-family: var(--mono);
            font-size: .9em;
            font-weight: 700;
            background: rgba(0,0,0,.06);
            padding: 1px 5px;
            border-radius: 3px;
        }

        /* ── Atoms ── */
        .empty  { color: var(--null); font-style: italic; padding: 6px 0; font-size: 13px; }
        .scalar { font-family: var(--mono); font-size: .88em; }
        .null   { color: var(--null); font-style: italic; }
        .bool-t { color: var(--bool-t); font-weight: 600; }
        .bool-f { color: var(--bool-f); font-weight: 600; }
        .count  { color: var(--muted); font-style: italic; font-size: .85em; }

        /* ── Key-value grid ── */
        .kv {
            display: grid;
            grid-template-columns: minmax(130px, 210px) 1fr;
            row-gap: 5px;
            column-gap: 18px;
            padding: 10px 0;
        }
        .kv dt {
            color: var(--muted);
            font-size: .78em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            align-self: start;
            padding-top: 2px;
        }
        .kv dd { word-break: break-word; }

        /* ── Collapsible section ── */
        .sec {
            border: 1px solid var(--border);
            border-radius: var(--r);
            background: var(--surface);
            overflow: hidden;
        }
        .sec__hdr {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            background: var(--surface);
            border: none;
            cursor: pointer;
            text-align: left;
        }
        .sec__hdr:hover { background: #f8fafc; }
        .sec__title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            flex: 1;
        }
        .sec__count {
            font-weight: 400;
            color: var(--muted);
            font-size: .9em;
        }
        .sec__chev {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            stroke: var(--muted);
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            transition: transform .18s ease;
        }
        .sec.closed .sec__chev { transform: rotate(-90deg); }
        .sec__body {
            padding: 0 14px 12px;
            border-top: 1px solid var(--border);
        }
        .sec.closed .sec__body { display: none; }

        /* ── Nested section inside section ── */
        .sec__body .sec {
            margin-top: 8px;
        }

        /* ── Table ── */
        .tbl-wrap { overflow-x: auto; }
        .tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: .86em;
        }
        .tbl th {
            text-align: left;
            padding: 7px 10px;
            background: #f8fafc;
            border-bottom: 2px solid var(--border);
            font-weight: 600;
            font-size: .76em;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            white-space: nowrap;
        }
        .tbl td {
            padding: 7px 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: #f8fafc; }
        .tbl b { font-weight: 600; color: var(--muted); font-size: .85em; }

        /* ── Simple list ── */
        .list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 6px 0;
        }
        .list li {
            padding: 4px 8px;
            background: #f8fafc;
            border-radius: 4px;
            font-size: .9em;
        }

        @media (max-width: 600px) {
            .kv { grid-template-columns: 1fr; }
            .kv dt { padding-top: 8px; }
            .kv dt:first-child { padding-top: 0; }
        }
        CSS;
    }

    // ── Embedded JS ───────────────────────────────────────────────────────────

    private function js(): string
    {
        return <<<'JS'
        function tog(id) {
            const el = document.getElementById(id);
            if (el) {
                const expanded = !el.classList.contains('closed');
                el.classList.toggle('closed', expanded);
                const btn = el.querySelector('.sec__hdr');
                if (btn) btn.setAttribute('aria-expanded', String(!expanded));
            }
        }
        JS;
    }
}
