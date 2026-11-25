<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->hasAdminAccess() || $user->belongsToCompany((int) $company->id);
    }

    public function manageTeam(User $user, Company $company): bool
    {
        return $user->canManageCompanies() || $user->isCompanyManager($company);
    }
}
