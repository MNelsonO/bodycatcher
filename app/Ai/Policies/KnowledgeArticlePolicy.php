<?php

namespace App\Ai\Policies;

use App\Ai\KnowledgeArticle;
use App\Models\User;
use App\Security\Role;

class KnowledgeArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->desk($user);
    }

    public function view(User $user, KnowledgeArticle $article): bool
    {
        return $this->desk($user);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Owner;
    }

    public function update(User $user, KnowledgeArticle $article): bool
    {
        return $user->role === Role::Owner;
    }

    private function desk(User $user): bool
    {
        return match ($user->role) {
            Role::Owner, Role::Sales => true,
        };
    }
}
