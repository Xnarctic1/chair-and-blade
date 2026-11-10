<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.index', [
            'appointments' => $request->user()->appointments()->with('service')->orderByDesc('starts_at')->paginate(10),
            'nextAppointment' => $request->user()->appointments()->with('service')->where('status', 'confirmed')->where('starts_at', '>', now())->orderBy('starts_at')->first(),
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        abort_unless($appointment->user_id === $request->user()->id || $request->user()->is_admin, 404);

        return view('account.appointment', ['appointment' => $appointment->load('service')]);
    }
}
