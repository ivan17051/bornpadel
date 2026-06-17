<?php

namespace App\Http\Middleware;

use App\Services\TournamentAccessService;
use Closure;
use Illuminate\Http\Request;

class ScopeUserTurnamen
{
    protected $tournamentAccess;

    public function __construct(TournamentAccessService $tournamentAccess)
    {
        $this->tournamentAccess = $tournamentAccess;
    }

    public function handle(Request $request, Closure $next)
    {
        $this->tournamentAccess->enforceRequestTurnamen($request);

        return $next($request);
    }
}
