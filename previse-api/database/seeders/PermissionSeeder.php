<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Az összes engedély definíciója modulonként.
     */
    public function run(): void
    {
        $permissions = [
            // Bejelentések
            'tickets' => [
                'read' => 'Bejelentések megtekintése',
                'create' => 'Bejelentés létrehozása',
                'update' => 'Bejelentés szerkesztése',
                'delete' => 'Bejelentés törlése',
                'assign' => 'Felelős kijelölése',
                'escalate' => 'Eszkaláció',
                'close' => 'Bejelentés lezárása',
                'manage_categories' => 'Kategóriák kezelése',
                'manage_statuses' => 'Státuszok kezelése',
            ],
            // Feladatok
            'tasks' => [
                'read' => 'Feladatok megtekintése',
                'create' => 'Feladat létrehozása',
                'update' => 'Feladat szerkesztése',
                'delete' => 'Feladat törlése',
                'assign' => 'Feladat kiosztása',
                'complete' => 'Feladat teljesítése',
                'manage_recurring' => 'Ismétlődő feladatok kezelése',
            ],
            // Projektek
            'projects' => [
                'read' => 'Projektek megtekintése',
                'create' => 'Projekt létrehozása',
                'update' => 'Projekt szerkesztése',
                'delete' => 'Projekt törlése',
                'manage_teams' => 'Csapatok kezelése',
                'manage_milestones' => 'Mérföldkövek kezelése',
                'change_status' => 'Projekt státusz módosítása',
            ],
            // Hibajegyek
            'issues' => [
                'read' => 'Hibajegyek megtekintése',
                'create' => 'Hibajegy létrehozása',
                'update' => 'Hibajegy szerkesztése',
                'delete' => 'Hibajegy törlése',
                'assign' => 'Felelős kijelölése',
                'resolve' => 'Megoldás rögzítése',
                'close' => 'Hibajegy lezárása',
            ],
            // Javaslatok
            'suggestions' => [
                'read' => 'Javaslatok megtekintése',
                'create' => 'Javaslat beküldése',
                'review' => 'Javaslat elbírálása',
                'vote' => 'Szavazás',
            ],
            // Dokumentumtár
            'documents' => [
                'read' => 'Dokumentumok megtekintése',
                'upload' => 'Dokumentum feltöltése',
                'download' => 'Dokumentum letöltése',
                'manage_folders' => 'Mappák kezelése',
            ],
            // Helyszínek (ML1-ML3)
            'locations' => [
                'read' => 'Helyszínek megtekintése',
                'create' => 'Helyszín létrehozása',
                'update' => 'Helyszín szerkesztése',
                'delete' => 'Helyszín törlése',
                'manage_floors' => 'Szintek kezelése',
                'manage_rooms' => 'Helyiségek kezelése',
                'manage_contacts' => 'Kontaktok kezelése',
                'manage_responsibles' => 'Felelősök kezelése',
                'manage_tags' => 'Címke-katalógus kezelése',
                'manage_types' => 'Típus-katalógus kezelése',
                'manage_floor_plans' => 'Alaprajzok kezelése',
                'import' => 'Helyszínek importálása',
                'export' => 'Helyszínek exportálása',
            ],
            // Eszközök
            'assets' => [
                'read' => 'Eszközök megtekintése',
                'create' => 'Eszköz létrehozása',
                'update' => 'Eszköz szerkesztése',
                'delete' => 'Eszköz törlése',
                'change_status' => 'Állapot módosítása',
                'manage_types' => 'Eszköz típusok kezelése',
                'generate_qr' => 'QR kód generálása',
            ],
            // Karbantartás
            'maintenance' => [
                'read' => 'Karbantartás megtekintése',
                'manage_schedules' => 'Ütemtervek kezelése',
                'log_work' => 'Munka naplózása',
            ],
            // Szerződések
            'contracts' => [
                'read' => 'Szerződések megtekintése',
                'create' => 'Szerződés létrehozása',
                'update' => 'Szerződés szerkesztése',
                'delete' => 'Szerződés törlése',
                'manage_contractors' => 'Alvállalkozók kezelése',
            ],
            // Felhasználók
            'users' => [
                'read' => 'Felhasználók megtekintése',
                'create' => 'Felhasználó meghívása',
                'edit' => 'Felhasználó szerkesztése',
                'deactivate' => 'Felhasználó deaktiválása',
                'manage_roles' => 'Szerepkörök kezelése',
            ],
            // Beállítások
            'settings' => [
                'manage_organization' => 'Szervezet beállítások kezelése',
                'manage_categories' => 'Kategóriák kezelése',
                'manage_sla' => 'SLA szabályok kezelése',
            ],
            // Riportok
            'reports' => [
                'view_dashboard' => 'Dashboard megtekintése',
                'export' => 'Riportok exportálása',
            ],
            // Üzenetek
            'messages' => [
                'send' => 'Üzenet küldése',
            ],
        ];

        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action => $description) {
                Permission::firstOrCreate(
                    ['module' => $module, 'action' => $action],
                    ['description' => $description]
                );
            }
        }
    }
}
