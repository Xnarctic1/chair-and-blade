<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminAppointmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->is_admin, 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:confirmed,pending_payment,cancelled,expired'],
        ]);
        $query = Appointment::with('service')->orderByDesc('starts_at');

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('reference', 'like', $search);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('admin.appointments', ['appointments' => $query->paginate(15)->withQueryString(), 'filters' => $filters]);
    }
}
