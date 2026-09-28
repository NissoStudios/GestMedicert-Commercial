<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Employment;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['code' => 'DEMO'],
            ['uuid' => (string) Str::uuid(), 'name' => 'GestMedicert Santé Entreprise']
        );

        $nkoteng = Site::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'NKT'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Nkoteng']
        );
        $mbandjock = Site::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'MBJ'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Mbandjock']
        );

        $medicalUnit = OrganizationalUnit::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'MED'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Service médical', 'type' => 'service']
        );
        $hrUnit = OrganizationalUnit::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'DRH'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Direction des ressources humaines', 'type' => 'direction']
        );

        $users = [
            ['name' => 'Administrateur GestMedicert', 'email' => 'admin@gestmedicert.local', 'role' => 'system-admin'],
            ['name' => 'Dr Médecin Démo', 'email' => 'medecin@gestmedicert.local', 'role' => 'doctor'],
            ['name' => 'Infirmier Démo', 'email' => 'infirmier@gestmedicert.local', 'role' => 'nurse'],
            ['name' => 'Pharmacien Démo', 'email' => 'pharmacie@gestmedicert.local', 'role' => 'pharmacist'],
            ['name' => 'Agent RH Démo', 'email' => 'rh@gestmedicert.local', 'role' => 'rh-hse'],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    ...$data,
                    'organization_id' => $organization->id,
                    'default_site_id' => $nkoteng->id,
                    'email_verified_at' => now(),
                    'password' => Hash::make('ChangeMe123!'),
                    'active' => true,
                    'must_change_password' => false,
                    'locale' => 'fr',
                ]
            );
            $user->syncRoles([$role]);
            $user->sites()->sync([
                $nkoteng->id => ['can_write' => true],
                $mbandjock->id => ['can_write' => true],
            ]);
        }

        $patient = Patient::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'patient_number' => 'PAT-DEMO-001'],
            [
                'primary_site_id' => $nkoteng->id,
                'uuid' => (string) Str::uuid(),
                'patient_type' => 'employee',
                'last_name' => 'MBOG',
                'first_name' => 'Amina',
                'normalized_last_name' => 'mbog',
                'normalized_first_name' => 'amina',
                'date_of_birth' => '1990-05-14',
                'sex' => 'female',
                'phone' => '690000001',
                'active' => true,
                'created_by' => User::query()->where('email', 'admin@gestmedicert.local')->value('id'),
            ]
        );

        Employment::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'employee_number' => 'EMP-0001'],
            [
                'patient_id' => $patient->id,
                'employment_type' => 'employee',
                'job_title' => 'Responsable qualité',
                'site_id' => $nkoteng->id,
                'organizational_unit_id' => $hrUnit->id,
                'employment_status' => 'active',
                'is_primary' => true,
                'started_on' => '2022-01-10',
            ]
        );

        $doctor = User::query()->where('email', 'medecin@gestmedicert.local')->firstOrFail();
        Appointment::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'patient_id' => $patient->id, 'scheduled_start' => today()->setTime(9, 0)],
            [
                'uuid' => (string) Str::uuid(),
                'site_id' => $nkoteng->id,
                'practitioner_id' => $doctor->id,
                'reason' => 'Consultation médicale',
                'scheduled_end' => today()->setTime(9, 30),
                'status' => 'confirmed',
                'source' => 'appointment',
                'created_by' => $doctor->id,
            ]
        );

        foreach ([$nkoteng, $mbandjock] as $site) {
            Warehouse::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'code' => 'PHA-'.$site->code],
                ['site_id' => $site->id, 'uuid' => (string) Str::uuid(), 'name' => 'Pharmacie '.$site->name]
            );
        }

        $products = [
            ['code' => 'MED-PARA500', 'type' => 'medication', 'inn' => 'Paracétamol', 'trade_name' => 'Paracétamol 500 mg', 'strength' => '500 mg', 'dosage_form' => 'Comprimé', 'unit' => 'comprimé', 'atc_code' => 'N02BE01', 'reorder_level' => 100],
            ['code' => 'MED-AMOX500', 'type' => 'medication', 'inn' => 'Amoxicilline', 'trade_name' => 'Amoxicilline 500 mg', 'strength' => '500 mg', 'dosage_form' => 'Gélule', 'unit' => 'gélule', 'atc_code' => 'J01CA04', 'reorder_level' => 50],
            ['code' => 'CON-GANT-M', 'type' => 'consumable', 'inn' => null, 'trade_name' => 'Gants d’examen M', 'strength' => null, 'dosage_form' => null, 'unit' => 'paire', 'atc_code' => null, 'reorder_level' => 200],
        ];

        foreach ($products as $product) {
            Product::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'code' => $product['code']],
                ['uuid' => (string) Str::uuid(), ...$product, 'active' => true]
            );
        }
    }
}
