<?php

namespace App\Security;

use App\Entity\CardCollection;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Une collection est privée : seul son propriétaire peut la voir et la modifier.
 *
 * @extends Voter<string, CardCollection>
 */
class CardCollectionVoter extends Voter
{
    public const string MANAGE = 'COLLECTION_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof CardCollection;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() === $subject->getOwner();
    }
}
