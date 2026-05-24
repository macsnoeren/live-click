<?php
require_once __DIR__ . '/DrumParser.php';

/**
 * DrumSvg — inline SVG renderer for drum structure notation.
 *
 * Key design principle:
 *   Each input symbol (|, -, ^, *) produces EXACTLY ONE vertical bar line.
 *   n symbols  →  n bar lines.  No extra opening/closing bars.
 *
 * Spacing:
 *   STEP = 20 px  between consecutive bars within a phrase group
 *   GAP  = 48 px  between the last bar of one group and the first of the next
 *   GAP > 2×STEP, so phrase boundaries are unmistakably wider.
 *
 * Annotations (drawn directly above/on the bar line's x position):
 *   -  →  RED horizontal line spanning all consecutive rest bars
 *   ^  →  GOLD inverted-V caret, peak exactly on the bar line
 *   *  →  ORANGE 4-spoke asterisk centred on the bar line
 *   |  →  plain bar line, no annotation
 */
class DrumSvg {
    // ── Layout ──────────────────────────────────────────────────────────────
    const LABEL_W    = 85;   // px — left label column
    const STEP       = 20;   // px — between consecutive bar lines within a group
    const GAP        = 48;   // px — between last bar of group N and first of group N+1
    const ROW_H      = 46;   // px — height per section row
    const ROW_GAP    = 8;    // px — vertical gap between rows
    const PAD_X      = 16;   // px — right padding
    const PAD_Y      = 8;    // px — top / bottom padding

    // ── Vertical positions within a row (relative to row top) ───────────────
    const ANNOT_Y = 10;   // y-centre of annotation zone
    const STAFF_T = 22;   // y where bar lines begin
    const STAFF_B = 40;   // y where bar lines end (= baseline)

    // ── Colours ─────────────────────────────────────────────────────────────
    const C_BAR      = '#d8d8d8'; // bar lines — bright, clearly visible
    const C_BASELINE = '#2a2a2a'; // faint horizontal baseline under all bars
    const C_LABEL    = '#888';    // section label text
    const C_REST     = '#cc0000'; // rest / quiet  (-)  RED
    const C_CRASH    = '#c8a000'; // cymbal / crash (^)  GOLD
    const C_BRAKE    = '#ff6600'; // brake / break  (*)  ORANGE
    const C_COMMENT  = '#666';    // inline comment text

    const COMMENT_GAP     = 14;   // px between last bar and comment text
    const COMMENT_CHAR_W  = 6.5;  // estimated px per character (11 px system-ui italic)

    // ────────────────────────────────────────────────────────────────────────

    public static function render(string $notation): string {
        $sections = DrumParser::parse($notation);
        if (empty($sections)) return '';

        $maxSpan = 0;
        foreach ($sections as $sec) {
            $s = self::contentSpan($sec['groups']);
            if (!empty($sec['comment'])) {
                $s += self::COMMENT_GAP + (int)ceil(strlen($sec['comment']) * self::COMMENT_CHAR_W);
            }
            if ($s > $maxSpan) $maxSpan = $s;
        }

        $totalW = self::LABEL_W + $maxSpan + self::PAD_X;
        $n      = count($sections);
        $totalH = self::PAD_Y * 2 + $n * self::ROW_H + ($n - 1) * self::ROW_GAP;

        // Hoogte is vast (alle rijen zichtbaar); breedte volgt via viewBox + height:auto.
        // JavaScript schaalt de hoogte naar de beschikbare ruimte.
        $o  = '<svg xmlns="http://www.w3.org/2000/svg"';
        $o .= ' viewBox="0 0 ' . $totalW . ' ' . $totalH . '"';
        $o .= ' data-natural-h="' . $totalH . '"';
        $o .= ' style="display:block;height:' . $totalH . 'px;width:auto">';

        $ry = self::PAD_Y;
        foreach ($sections as $sec) {
            $o  .= self::renderRow($sec, $ry);
            $ry += self::ROW_H + self::ROW_GAP;
        }

        $o .= '</svg>';
        return $o;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /**
     * Pixel span from x of first symbol to x of last symbol.
     * (n-1) gaps total: (ngr-1) are GAP, the rest are STEP.
     */
    private static function contentSpan(array $groups): int {
        $n   = 0;
        foreach ($groups as $g) $n += count($g);
        if ($n <= 1) return 0;
        $ngr = count($groups);
        return ($n - $ngr) * self::STEP + ($ngr - 1) * self::GAP;
    }

    private static function renderRow(array $sec, int $ry): string {
        $o      = '';
        $label  = $sec['label'] ?? '';
        $groups = $sec['groups'] ?? [];
        if (empty($groups)) return '';

        $bt = $ry + self::STAFF_T;   // absolute top of bar lines
        $bl = $ry + self::STAFF_B;   // absolute bottom / baseline
        $ay = $ry + self::ANNOT_Y;   // absolute annotation y-centre

        // ── Build flat symbol list with absolute x positions ──────────────
        $syms   = [];  // [['type'=>char, 'x'=>float], ...]
        $prevGi = -1;
        $x      = (float)self::LABEL_W;

        foreach ($groups as $gi => $group) {
            foreach ($group as $type) {
                if ($prevGi >= 0) {
                    $x += ($gi !== $prevGi) ? self::GAP : self::STEP;
                }
                $syms[]  = ['type' => $type, 'x' => $x];
                $prevGi  = $gi;
            }
        }
        if (empty($syms)) return '';

        // ── Section label ─────────────────────────────────────────────────
        if ($label !== '') {
            $o .= '<text'
                . ' x="' . (self::LABEL_W - 4) . '"'
                . ' y="' . ($bl - 2) . '"'
                . ' text-anchor="end"'
                . ' font-family="system-ui,Arial,sans-serif"'
                . ' font-size="11" fill="' . self::C_LABEL . '">'
                . htmlspecialchars($label, ENT_XML1) . ':</text>';
        }

        // ── Faint baseline (first bar → last bar) ─────────────────────────
        $xFirst = $syms[0]['x'];
        $xLast  = $syms[count($syms) - 1]['x'];
        $o .= '<line x1="' . $xFirst . '" y1="' . $bl . '"'
            . ' x2="' . $xLast . '" y2="' . $bl . '"'
            . ' stroke="' . self::C_BASELINE . '" stroke-width="1"/>';

        // ── Detect consecutive rest (-) spans ─────────────────────────────
        $restSpans = [];
        $spanX     = null;
        $spanXEnd  = null;
        foreach ($syms as $s) {
            if ($s['type'] === '-') {
                if ($spanX === null) $spanX = $s['x'];
                $spanXEnd = $s['x'];
            } else {
                if ($spanX !== null) {
                    $restSpans[] = [$spanX, $spanXEnd];
                    $spanX = null;
                }
            }
        }
        if ($spanX !== null) $restSpans[] = [$spanX, $spanXEnd];

        // ── Draw one vertical bar line per symbol ─────────────────────────
        foreach ($syms as $s) {
            $o .= '<line'
                . ' x1="' . $s['x'] . '" y1="' . $bt . '"'
                . ' x2="' . $s['x'] . '" y2="' . $bl . '"'
                . ' stroke="' . self::C_BAR . '" stroke-width="1.5"/>';
        }

        // ── Rest span lines (RED, directly above bar lines) ───────────────
        foreach ($restSpans as [$x1, $x2]) {
            if ($x1 === $x2) {
                // Single rest bar: short centred line
                $o .= '<line x1="' . ($x1 - 7) . '" y1="' . $ay . '"'
                    . ' x2="' . ($x1 + 7) . '" y2="' . $ay . '"'
                    . ' stroke="' . self::C_REST . '" stroke-width="3"'
                    . ' stroke-linecap="round"/>';
            } else {
                // Multi-bar rest: line from first to last rest bar position
                $o .= '<line x1="' . $x1 . '" y1="' . $ay . '"'
                    . ' x2="' . $x2 . '" y2="' . $ay . '"'
                    . ' stroke="' . self::C_REST . '" stroke-width="3"'
                    . ' stroke-linecap="square"/>';
            }
        }

        // ── Crash (^) and brake (*) symbols, centred on their bar line x ──
        foreach ($syms as $s) {
            $sx = $s['x'];

            if ($s['type'] === '^') {
                // Gold inverted-V caret — peak exactly on bar line x
                $hw = 8; $hh = 8;
                $o .= '<polyline'
                    . ' points="'
                    .   ($sx - $hw) . ',' . ($ay + $hh) . ' '
                    .   $sx         . ',' . ($ay - $hh) . ' '
                    .   ($sx + $hw) . ',' . ($ay + $hh)
                    . '"'
                    . ' fill="none"'
                    . ' stroke="' . self::C_CRASH . '"'
                    . ' stroke-width="2.5"'
                    . ' stroke-linejoin="miter"/>';

            } elseif ($s['type'] === '*') {
                // Orange 4-spoke asterisk — centred on bar line x
                $d  = 7.0;
                $d2 = 5.0;
                foreach ([
                    [$sx,       $ay - $d,  $sx,       $ay + $d ],
                    [$sx - $d,  $ay,       $sx + $d,  $ay      ],
                    [$sx - $d2, $ay - $d2, $sx + $d2, $ay + $d2],
                    [$sx + $d2, $ay - $d2, $sx - $d2, $ay + $d2],
                ] as [$x1, $y1, $x2, $y2]) {
                    $o .= '<line'
                        . ' x1="' . $x1 . '" y1="' . $y1 . '"'
                        . ' x2="' . $x2 . '" y2="' . $y2 . '"'
                        . ' stroke="' . self::C_BRAKE . '" stroke-width="2"'
                        . ' stroke-linecap="round"/>';
                }
            }
        }

        // ── Inline comment (italic, right of last bar) ────────────────────
        if (!empty($sec['comment'])) {
            $o .= '<text'
                . ' x="' . ($xLast + self::COMMENT_GAP) . '"'
                . ' y="' . ($bl - 2) . '"'
                . ' font-family="system-ui,Arial,sans-serif"'
                . ' font-size="11"'
                . ' font-style="italic"'
                . ' fill="' . self::C_COMMENT . '">'
                . htmlspecialchars($sec['comment'], ENT_XML1) . '</text>';
        }

        return $o;
    }
}
