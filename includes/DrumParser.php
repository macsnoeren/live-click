<?php
/**
 * DrumParser — parse drummer's shorthand notation into sections + phrase groups.
 *
 * Format (one section per line):
 *   SectionLabel: | | | |   | | | |    | | | |   | | | |
 *
 * Measure symbols:
 *   |  = normal bar
 *   -  = quiet / rest   (renders as horizontal dash above)
 *   ^  = cymbal / crash (renders as X notehead above)
 *   *  = brake / break  (renders as asterisk above)
 *
 * Phrase grouping: 2+ spaces between symbols create a phrase boundary.
 */
class DrumParser {
    const VALID = ['|', '-', '^', '*'];

    /**
     * @return array[] Array of sections: [['label'=>string, 'groups'=>[[char,...],...]],...]
     */
    public static function parse(string $notation): array {
        $sections = [];
        foreach (preg_split('/\r?\n/', $notation) as $line) {
            $line = rtrim($line);
            if ($line === '') continue;

            // Optional "Label: content" format
            if (strpos($line, ':') !== false) {
                [$labelPart, $contentPart] = explode(':', $line, 2);
                $label   = trim($labelPart);
                $content = $contentPart; // preserve leading spaces (they matter for grouping)
            } else {
                $label   = '';
                $content = $line;
            }

            // Anything after the last valid symbol (non-symbol, non-whitespace) is a comment
            $comment = '';
            if (preg_match('/^([-|*^ \t]*)([^-|*^ \t].*)$/', rtrim($content), $m)) {
                $content = $m[1];
                $comment = trim($m[2]);
            }

            $groups = self::tokenizeGroups($content);
            if (!empty($groups)) {
                $sections[] = ['label' => $label, 'groups' => $groups, 'comment' => $comment];
            }
        }
        return $sections;
    }

    private static function tokenizeGroups(string $content): array {
        $groups  = [];
        $current = [];
        $i       = 0;
        $len     = strlen($content);

        while ($i < $len) {
            $c = $content[$i];
            if (in_array($c, self::VALID, true)) {
                $current[] = $c;
                $i++;
            } elseif ($c === ' ' || $c === "\t") {
                // Count consecutive whitespace
                $start = $i;
                while ($i < $len && ($content[$i] === ' ' || $content[$i] === "\t")) {
                    $i++;
                }
                // 2+ spaces → phrase group boundary
                if (($i - $start) >= 2 && !empty($current)) {
                    $groups[]  = $current;
                    $current   = [];
                }
            } else {
                $i++; // skip unknown char
            }
        }

        if (!empty($current)) {
            $groups[] = $current;
        }
        return $groups;
    }
}
