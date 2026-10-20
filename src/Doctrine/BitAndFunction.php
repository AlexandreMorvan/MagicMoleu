<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * BIT_AND(a, b) en DQL → (a & b) en SQL, pour filtrer sur les bitmasks de couleurs.
 */
class BitAndFunction extends FunctionNode
{
    private Node|string $left;
    private Node|string $right;

    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            '(%s & %s)',
            $sqlWalker->walkSimpleArithmeticExpression($this->left),
            $sqlWalker->walkSimpleArithmeticExpression($this->right),
        );
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->left = $parser->SimpleArithmeticExpression();
        $parser->match(TokenType::T_COMMA);
        $this->right = $parser->SimpleArithmeticExpression();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
