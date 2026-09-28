<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Appointment::query()
            ->with(['patient:id,uuid,patient_number,last_name,first_name', 'site:id,name', 'practitioner:id,name'])
            ->where('organization_id', $this->organizationId($request))
            ->whereIn('site_id', $this->user($request)->accessibleSiteIds());

        if ($request->filled('date')) {
            $query->whereDate('scheduled_start', $request->date('date'));
        } else {
            $query->whereDate('scheduled_start', '>=', today());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $appointments = $query->orderBy('scheduled_start')->paginate(20)->withQueryString();
        if (! $request->user()->can('clinical-data.view')) {
            $appointments->through(function ($appointment) {
                $appointment->reason = 'Rendez-vous médical';
                $appointment->notes = null;

                return $appointment;
            });
        }

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'filters' => $request->only('date', 'status'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Appointments/Form', $this->formOptions($request));
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('organization_id', $organizationId)],
            'site_id' => ['required', Rule::exists('sites', 'id')->where('organization_id', $organizationId)],
            'practitioner_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $organizationId)],
            'reason' => ['required', 'string', 'max:255'],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'],
            'source' => ['required', Rule::in(['appointment', 'walk_in'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->ensureSiteAccess($request, (int) $data['site_id'], true);

        $appointment = Appointment::query()->create([
            ...$data,
            'organization_id' => $organizationId,
            'status' => $data['source'] === 'walk_in' ? 'waiting' : 'scheduled',
            'arrived_at' => $data['source'] === 'walk_in' ? now() : null,
            'created_by' => $request->user()->id,
        ]);
        $audit->record('appointment.created', $request->user(), $appointment, $appointment->patient_id, request: $request);

        return redirect()->route('appointments.index')->with('success', 'Rendez-vous enregistré.');
    }

    public function show(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->ensureOrganization($request, $appointment);

        return redirect()->route('appointments.index');
    }

    public function edit(Request $request, Appointment $appointment): Response
    {
        $this->ensureOrganization($request, $appointment);

        return Inertia::render('Appointments/Form', [
            ...$this->formOptions($request),
            'appointment' => $appointment,
        ]);
    }

    public function update(Request $request, Appointment $appointment, AuditService $audit): RedirectResponse
    {
        $this->ensureOrganization($request, $appointment);
        $data = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'confirmed', 'waiting', 'called', 'in_consultation', 'completed', 'cancelled', 'no_show'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $changes = $data;
        if ($data['status'] === 'waiting' && ! $appointment->arrived_at) {
            $changes['arrived_at'] = now();
        }
        if ($data['status'] === 'called' && ! $appointment->called_at) {
            $changes['called_at'] = now();
        }
        $appointment->update($changes);
        $audit->record('appointment.status-changed', $request->user(), $appointment, $appointment->patient_id, $data, $request);

        return back()->with('success', 'Statut du rendez-vous mis à jour.');
    }

    public function destroy(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->ensureOrganization($request, $appointment);
        abort_unless(in_array($appointment->status, ['scheduled', 'cancelled'], true), 422);
        $appointment->delete();

        return back()->with('success', 'Rendez-vous supprimé.');
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        $organizationId = $this->organizationId($request);
        $siteIds = $this->user($request)->accessibleSiteIds();

        return [
            'appointment' => null,
            'patients' => Patient::query()->where('organization_id', $organizationId)->whereIn('primary_site_id', $siteIds)->where('active', true)->orderBy('last_name')->get(['id', 'uuid', 'patient_number', 'last_name', 'first_name']),
            'sites' => Site::query()->where('organization_id', $organizationId)->whereIn('id', $siteIds)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'practitioners' => User::query()->where('organization_id', $organizationId)->where('active', true)->role(['doctor', 'nurse'])->orderBy('name')->get(['id', 'name']),
        ];
    }
}
