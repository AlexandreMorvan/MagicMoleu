<?php

namespace App\Security;

use App\Entity\Deck;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Un deck public est visible de tous ; seul son propriétaire peut le voir s'il est privé, et le modifier.
 *
 * @extends Voter<string, Deck>
 */
class DeckVoter extends Voter
{
    public const string VIEW = 'DECK_VIEW';
    public const string EDIT = 'DECK_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof Deck;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $isOwner = $token->getUser() === $subject->getOwner();

        return match ($attribute) {
            self::VIEW => $subject->isPublic() || $isOwner,
            self::EDIT => $isOwner,
            default => false,
        };
    }
}
