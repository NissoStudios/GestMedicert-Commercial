<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'patients.view',
            'patients.create',
            'patients.update',
            'patients.archive',
            'patients.override-duplicates',
            'clinical-data.view',
            'appointments.view',
            'appointments.manage',
            'consultations.view',
            'consultations.create',
            'consultations.update',
            'consultations.finalize',
            'consultations.finalize-any',
            'consultations.addendum',
            'prescriptions.issue',
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',
            'documents.submit',
            'documents.approve',
            'documents.self-approve',
            'documents.reject',
            'documents.revoke',
            'documents.download',
            'pharmacy.view',
            'products.manage',
            'stock.receive',
            'pharmacy.dispense',
            'reports.view',
            'audit.view',
            'administration.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'system-admin' => $permissions,
            'organization-admin' => [
                'dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'patients.archive',
                'appointments.view', 'appointments.manage', 'documents.view', 'reports.view',
                'products.manage', 'pharmacy.view', 'stock.receive', 'audit.view', 'administration.manage',
            ],
            'doctor' => [
                'dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'patients.override-duplicates',
                'clinical-data.view', 'appointments.view', 'appointments.manage', 'consultations.view',
                'consultations.create', 'consultations.update', 'consultations.finalize', 'consultations.addendum',
                'prescriptions.issue', 'documents.view', 'documents.create', 'documents.update', 'documents.delete',
                'documents.submit', 'documents.approve', 'documents.reject', 'documents.revoke',
                'documents.download', 'reports.view',
            ],
            'nurse' => [
                'dashboard.view', 'patients.view', 'patients.create', 'patients.update', 'clinical-data.view',
                'appointments.view', 'appointments.manage', 'consultations.view', 'consultations.create',
                'consultations.update', 'consultations.addendum', 'documents.view', 'documents.create',
                'documents.update', 'documents.submit', 'documents.download',
            ],
            'pharmacist' => [
                'dashboard.view', 'pharmacy.view', 'products.manage', 'stock.receive', 'pharmacy.dispense', 'reports.view',
            ],
            'rh-hse' => [
                'dashboard.view', 'patients.view', 'patients.create', 'patients.update',
                'appointments.view', 'appointments.manage', 'documents.view', 'reports.view',
            ],
            'manager' => ['dashboard.view', 'patients.view', 'appointments.view', 'reports.view'],
            'auditor' => ['dashboard.view', 'reports.view', 'audit.view'],
        ];

        foreach ($roles as $name => $rolePermissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($rolePermissions);
        }
    }
}
