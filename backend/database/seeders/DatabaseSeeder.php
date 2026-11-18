<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Organisation;
use App\Models\Position;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Title;
use App\Models\User;
use App\Models\UserInfo;
use App\Services\DocumentFlow;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Permissions + default roles (safe to run every time)
        $this->call(RbacSeeder::class);

        // Seed only once, so restarting the container never overwrites your data.
        if (User::where('email', 'admin@odf.local')->exists()) {
            $this->command?->info('Demo data already present - skipping seeder.');

            return;
        }

        // ---- App settings (App Seetings screen) ----
        foreach ([
            ['app_name', 'ODF', null],
            ['app_full_name', 'Organization Document Flow', null],
            ['logo', 'Logo', null],
            ['edit_hour', '24', 'Document can edit in duration (hour).'],
            ['dateline_warning', '24', 'Dateline warning with hour'],
            ['org_name_kh', 'មន្ទីរពេទ្យកាល់ម៉ែត', 'Organisation name printed on documents'],
            ['org_name_en', 'HOPITAL Calmette', 'Organisation name printed on documents'],
            ['ministry_kh', 'ក្រសួងសុខាភិបាល', 'Ministry printed on documents'],
        ] as [$name, $value, $desc]) {
            AppSetting::updateOrCreate(['name' => $name], ['value' => $value, 'description' => $desc]);
        }

        // ---- Document types ----
        foreach ([
            ['normal_view', 'Normal View', 'ទិដ្ឋភាពធម្មតា'],
            ['status_view', 'Status View', 'ទិដ្ឋភាពស្ថានភាព'],
            ['inheritance_view', 'Inheritance View', 'ទិដ្ឋភាពបន្ត'],
            ['public_view', 'Public View', 'ទិដ្ឋភាពសាធារណៈ'],
        ] as [$value, $name, $kh]) {
            DocumentType::updateOrCreate(['value' => $value], ['name' => $name, 'name_kh' => $kh]);
        }

        // ---- Titles ----
        $titles = [];
        foreach ([
            ['H.E. Professor', 'ឯកឧត្តមសាស្ត្រាចារ្យ', 'H.E. Prof.', 'ឯ.ឧ.សាស្ត្រាចារ្យ'],
            ['L.C Professor', 'លោកជំទាវសាស្ត្រាចារ្យ', null, null],
            ['Professor', 'សាស្ត្រាចារ្យ', 'Prof.', 'សាស្ត្រាចារ្យ'],
            ['Doctor', 'វេជ្ជបណ្ឌិត', 'Dr.', 'វ.'],
            ['Doctor of Pharmacy', 'ឱសថបណ្ឌិត', 'PharmD', null],
            ['Pharmacist', 'កញ្ញាឱសថការី', 'PH', null],
            ['Mister', 'លោក', 'Mr.', 'លោក'],
            ['Madam', 'លោកស្រី', 'Mrs.', 'លោកស្រី'],
            ['Miss', 'កញ្ញា', 'Ms.', 'កញ្ញា'],
        ] as [$n, $kh, $a, $akh]) {
            $titles[$n] = Title::updateOrCreate(['name' => $n], ['name_kh' => $kh, 'abbr' => $a, 'abbr_kh' => $akh]);
        }

        // ---- Positions ----
        $positions = [];
        foreach ([
            ['Director General', 'អគ្គនាយកមន្ទីរពេទ្យ'],
            ['Deputy Director General', 'អគ្គនាយករងមន្ទីរពេទ្យ'],
            ['Chief of Administration Office', 'ប្រធានការិយាល័យរដ្ឋបាល'],
            ['Chief of General Affairs', 'ប្រធានក្រុមការងារទូទៅ'],
            ['Chief of Electrical Engineering', 'ប្រធានផ្នែកបច្ចេកទេសអេឡិចត្រូនិច'],
            ['Chef Unite Oncologie', 'ប្រធានអ៊ុយនីតេ'],
            ['Chef Unite Chirurgie', 'ប្រធានអ៊ុយនីតេវះកាត់ទ្រូង និងសួត'],
            ['Conseiller, Direction Générale', 'ទីប្រឹក្សាអមថ្នាក់ដឹកនាំ'],
            ['Assistant Office', 'អនុប្រធាន'],
            ['Doctor Specialist', 'វេជ្ជបណ្ឌិតឯកទេស'],
            ['Officer', 'មន្ត្រី'],
            ['Chief of security guard', 'ប្រធានសន្តិសុខ'],
            ['Secretary', 'លេខាធិការ'],
        ] as [$n, $kh]) {
            $positions[$n] = Position::updateOrCreate(['name' => $n], ['name_kh' => $kh, 'active' => true]);
        }

        // ---- Departments / categories / organisations ----
        $admin = Department::updateOrCreate(['name' => 'Administration'], ['name_kh' => 'រដ្ឋបាល', 'abbr' => 'ADM', 'abbr_kh' => 'រ.ប', 'code' => '52']);
        $tech = Department::updateOrCreate(['name' => 'Technical'], ['name_kh' => 'បច្ចេកទេស', 'abbr' => 'TECH', 'abbr_kh' => 'ប.ទ', 'code' => '41']);
        Department::updateOrCreate(['name' => 'Information Technology'], ['name_kh' => 'ក្រុមការងារព័ត៌មានវិទ្យា', 'abbr' => 'IT', 'abbr_kh' => 'ព.វ']);
        Department::updateOrCreate(['name' => 'CMC'], ['name_kh' => 'ប្រធានគណៈកម្មការវេជ្ជសាស្ត្រពិគ្រោះយោបល់', 'abbr' => 'CMC', 'abbr_kh' => 'CMC']);

        foreach ([['Request', 'សំណើ'], ['Report', 'របាយការណ៍'], ['Invitation', 'លិខិតអញ្ជើញ'], ['Announcement', 'សេចក្តីជូនដំណឹង']] as [$n, $kh]) {
            Category::updateOrCreate(['name' => $n], ['name_kh' => $kh]);
        }
        foreach ([['Ministry of Health', 'ក្រសួងសុខាភិបាល'], ['Office of the Council of Ministers', 'ទីស្តីការគណៈរដ្ឋមន្ត្រី']] as [$n, $kh]) {
            Organisation::updateOrCreate(['name' => $n], ['name_kh' => $kh]);
        }

        // ---- Users (default password: password) ----
        $roleIds = Role::pluck('id', 'title');
        $roleMap = ['admin' => 'Admin', 'secretary' => 'Moderator', 'staff' => 'User', 'boss' => 'Direction'];
        $mk = function ($email, $name, $nameKh, $role, $title, $position, $dept = null) use ($titles, $positions, $roleIds, $roleMap) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'name_kh' => $nameKh, 'username' => strtok($email, '@'), 'password' => 'password',
                'email_verified_at' => now(),
                'title_id' => $titles[$title]->id ?? null,
                'position_id' => $positions[$position]->id ?? null,
                'department_id' => $dept?->id, 'active' => true,
            ]);
            $user->roles()->sync([$roleIds[$roleMap[$role]]]);
            UserInfo::updateOrCreate(['user_id' => $user->id], ['gender' => 'Male']);

            return $user;
        };

        $mk('admin@odf.local', 'System Admin', 'អ្នកគ្រប់គ្រងប្រព័ន្ធ', 'admin', 'Mister', 'Officer', $admin);
        $secretary = $mk('secretary@odf.local', 'SIN VONGKANIKA', 'ស៊ិន វង្សកណិកា', 'secretary', 'Miss', 'Secretary', $admin);
        $mao = $mk('mao.reasey@odf.local', 'Mao Reasey', 'ម៉ៅ រាសី', 'staff', 'Mister', 'Chief of Administration Office', $admin);
        $sam = $mk('sam.leak@odf.local', 'SAM LEAK', 'សាម លក្ខណ៍', 'staff', 'Mister', 'Chief of General Affairs', $admin);
        $chhom = $mk('chhom.sakborey@odf.local', 'CHHOM SAKBOREY', 'ឆោម សាក់បូរី', 'staff', 'Mister', 'Chief of Electrical Engineering', $tech);
        $hun = $mk('hun.chamroeun@odf.local', 'HUN CHAMROEUN', 'ហ៊ុន ចំរើន', 'staff', 'H.E. Professor', 'Deputy Director General');
        $boss = $mk('boss@odf.local', 'KONG SONYA', 'គង់ សន្យា', 'boss', 'H.E. Professor', 'Director General');

        // ---- Staff list ----
        foreach ([
            ['00001', 'CHHEANG RA', 'ឈាង រ៉ា', 'H.E. Professor'],
            ['00002', 'KONG SONYA', 'គង់ សន្យា', 'H.E. Professor', $boss],
            ['00003', 'MAO REASEY', 'ម៉ៅ រាសី', 'Mister', $mao],
            ['00006', 'HENG SAMBATH', 'ហេង សម្បត្តិ', 'H.E. Professor'],
            ['00007', 'VA MENG', 'វ៉ា ម៉េង', 'Mister'],
        ] as $s) {
            Staff::updateOrCreate(['staff_code' => $s[0]], [
                'name' => $s[1], 'name_kh' => $s[2], 'gender' => 'Male',
                'title_id' => $titles[$s[3]]->id, 'user_id' => ($s[4] ?? null)?->id,
            ]);
        }

        // ---- One demo document already moving through the flow ----
        if (! Document::exists()) {
            auth()->setUser($secretary);
            $doc = Document::create([
                'letter_no' => null,
                'incoming_code' => '2355កម/2026',
                'document_code' => '52-59-0040',
                'from_source' => 'កងវិស្វកម្មតេជោ ហ៊ុន សែន សៀមរាប',
                'description' => '<p>កម្មវត្ថុ: សំណើសុំការពិនិត្យ និងសម្រេចការស្នើសុំបិទផ្លូវលេខ៨០ ជាប់ស្ថានទូតបារាំង ដើម្បីយកទីតាំងស្ទូចម៉ាស៊ីន។</p>',
                'document_type_id' => DocumentType::where('value', 'normal_view')->value('id'),
                'dateline' => now()->addDays(2),
                'created_by' => $secretary->id,
            ]);
            $doc->log('created', null, $secretary->id);
            DocumentFlow::build($doc, [$mao->id, $sam->id, $chhom->id, $hun->id], $boss->id);

            // Mao Reasey already commented, so it is now SAM LEAK's turn.
            $first = $doc->comments()->where('status', 'current')->first();
            auth()->setUser($mao);
            DocumentFlow::submit($doc, $first, '<ul><li>បានពិនិត្យសំណើរបស់ ក្រុមហ៊ុនសាងសង់កងវិស្វកម្មតេជោ ហ៊ុន សែន សៀមរាប។</li><li>បានឃើញយោបល់ពាក់ព័ន្ធ។</li><li>គោរពជូនឯកឧត្តមអគ្គនាយកមេត្តាពិនិត្យសម្រេច។</li></ul>');
        }
    }
}
