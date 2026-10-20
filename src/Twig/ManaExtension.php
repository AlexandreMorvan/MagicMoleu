<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

class ManaExtension
{
    private const string SYMBOL_URI = 'https://svgs.scryfall.io/card-symbols/%s.svg';

    /**
     * Remplace les symboles {X}, {2}{U}, {W/P}, {T}… par les icônes de mana Scryfall.
     * Le texte est échappé avant remplacement, les retours à la ligne deviennent des <br>.
     */
    #[AsTwigFilter('mana_symbols', isSafe: ['html'])]
    public function manaSymbols(?string $text): string
    {
        if (null === $text || '' === $text) {
            return '';
        }

        $html = preg_replace_callback('/\{([^}\s]{1,6})\}/', static function (array $m): string {
            $code = strtoupper(str_replace('/', '', $m[1]));

            return \sprintf(
                '<img class="mana-symbol" src="%s" alt="{%s}" title="{%s}" loading="lazy">',
                \sprintf(self::SYMBOL_URI, rawurlencode($code)),
                $m[1],
                $m[1],
            );
        }, htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));

        return nl2br($html, false);
    }
}
