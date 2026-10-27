<?php

namespace Database\Seeders;

use App\Actions\CreateWorkspace;
use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Attendance;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Letter;
use App\Models\Message;
use App\Models\Minute;
use App\Models\MinuteAction;
use App\Models\MinuteParticipant;
use App\Models\Note;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local demo data. Every demo account uses the password in DEMO_PASSWORD
 * (see .env.example). Never run this seeder in production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(CreateWorkspace $createWorkspace): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Demo seeder is disabled in production.');

            return;
        }

        $password = env('DEMO_PASSWORD', 'Daftar-Demo-2026!');

        $people = [
            ['احسان رضایی', 'ehsan@daftar.test', 'مدیر فنی'],
            ['سارا محمدی', 'sara@daftar.test', 'طراح محصول'],
            ['علی کریمی', 'ali@daftar.test', 'توسعه‌دهنده بک‌اند'],
            ['مریم حسینی', 'maryam@daftar.test', 'کارشناس فروش'],
            ['رضا احمدی', 'reza@daftar.test', 'حسابدار'],
        ];

        $users = [];
        $titles = [];

        foreach ($people as [$name, $email, $title]) {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now(), 'last_seen_at' => now()->subMinutes(rand(0, 400))])->save();
            $users[] = $user;
            $titles[$user->id] = $title;
        }

        [$ehsan, $sara, $ali, $maryam, $reza] = $users;

        $dev = $createWorkspace($ehsan, 'برنامه نویسی', 'amber');
        $createWorkspace($ehsan, 'حسابداری', 'emerald')->addMember($reza, WorkspaceRole::Admin);
        $createWorkspace($ehsan, 'شخصی', 'violet');

        $ehsan->forceFill(['current_workspace_id' => $dev->id, 'is_admin' => true])->save();

        $dev->addMember($sara, WorkspaceRole::Admin);
        $dev->addMember($ali);
        $dev->addMember($maryam);
        $dev->addMember($reza, WorkspaceRole::Guest);

        foreach ($titles as $id => $title) {
            $dev->members()->updateExistingPivot($id, ['job_title' => $title]);
        }

        $taskLabels = collect([['فوری', 'rose'], ['باگ', 'orange'], ['بهبود', 'sky'], ['مشتری', 'violet']])
            ->map(fn ($l) => $dev->labels()->create(['scope' => 'task', 'name' => $l[0], 'color' => $l[1]]));
        $dev->labels()->create(['scope' => 'letter', 'name' => 'قرارداد', 'color' => 'teal']);
        $dev->labels()->create(['scope' => 'customer', 'name' => 'VIP', 'color' => 'amber']);
        $webCategory = $dev->categories()->create(['name' => 'ساخت سایت', 'color' => 'teal']);

        // Project with a board ------------------------------------------------
        $clinic = new Project([
            'name' => 'وبسایت کلینیک حامی',
            'description' => 'طراحی و پیاده‌سازی وبسایت کلینیک با نوبت‌دهی آنلاین.',
            'color' => 'teal',
            'visibility' => 'members',
            'starts_on' => now()->subWeeks(2),
            'due_on' => now()->addWeeks(3),
        ]);
        $clinic->workspace_id = $dev->id;
        $clinic->created_by = $ehsan->id;
        $clinic->save();
        $clinic->createDefaultColumns();
        $clinic->members()->sync([
            $ehsan->id => ['role' => ProjectRole::Manager->value],
            $sara->id => ['role' => ProjectRole::Member->value],
            $ali->id => ['role' => ProjectRole::Member->value],
            $reza->id => ['role' => ProjectRole::Viewer->value],
        ]);
        $columns = $clinic->columns()->get();

        $make = function (array $attrs, array $assignees, int $column, array $checklist = [], array $labels = []) use ($dev, $clinic, $columns, $ehsan, $webCategory) {
            $task = new Task($attrs);
            $task->workspace_id = $dev->id;
            $task->project_id = $clinic->id;
            $task->column_id = $columns[$column]->id;
            $task->category_id = $webCategory->id;
            $task->creator_id = $ehsan->id;
            $task->position = rand(1, 100) * 1024;

            if ($columns[$column]->is_done) {
                $task->completed_at = now()->subDays(rand(0, 6));
                $task->completed_by = $assignees[0];
            }

            $task->save();
            $task->assignees()->sync($assignees);
            $task->labels()->sync($labels);

            foreach ($checklist as $i => [$body, $done]) {
                $task->checklist()->create(['body' => $body, 'is_done' => $done, 'position' => $i]);
            }

            $task->log('created', user: $ehsan);

            return $task;
        };

        $main = $make(['title' => 'وبسایت کلینیک حامی', 'priority' => 2, 'due_at' => now()->addDays(5)->setTime(17, 0)], [$ehsan->id], 1, [
            ['ssl fix', false], ['محتوا', false], ['ادیت', false], ['یافتن عکس ها', true], ['ساخت لوگو', true], ['ساخت سایت مپ', false], ['اطلاع به خاله', false],
        ]);
        $make(['title' => 'طراحی صفحه نوبت‌دهی', 'priority' => 3, 'due_at' => now()->subDay()->setTime(12, 0)], [$sara->id], 1, [['وایرفریم', true], ['طرح نهایی', false]], [$taskLabels[0]->id]);
        $make(['title' => 'اتصال درگاه پرداخت', 'priority' => 2, 'due_at' => now()->addDays(2)], [$ali->id], 0, [], [$taskLabels[3]->id]);
        $make(['title' => 'رفع مشکل نمایش موبایل', 'priority' => 1, 'due_at' => now()->setTime(18, 0)], [$ali->id, $ehsan->id], 2, [], [$taskLabels[1]->id]);
        $make(['title' => 'انتخاب پالت رنگی', 'priority' => 1], [$sara->id], 3);
        $make(['title' => 'خرید هاست و دامنه', 'priority' => 0], [$ehsan->id], 3);
        $make(['title' => 'بهینه‌سازی سئو صفحات', 'priority' => 1, 'due_at' => now()->addDays(9)], [$maryam->id], 0, [], [$taskLabels[2]->id]);

        $comment = new TaskComment(['body' => 'لوگو و عکس‌ها آماده شد. منتظر محتوای صفحه درباره ما هستیم.', 'progress' => 30]);
        $comment->task_id = $main->id;
        $comment->user_id = $sara->id;
        $comment->save();

        // Personal tasks (no project)
        foreach ([['پیگیری فاکتور سرور', $reza, $ehsan, 1], ['آماده‌سازی جلسه هفتگی', $ehsan, $sara, 0], ['تماس با مشتری جدید', $maryam, $ehsan, 3]] as [$title, $creator, $assignee, $days]) {
            $t = new Task(['title' => $title, 'priority' => 1, 'due_at' => now()->addDays($days)->setTime(10, 0)]);
            $t->workspace_id = $dev->id;
            $t->creator_id = $creator->id;
            $t->save();
            $t->assignees()->sync([$assignee->id]);
        }

        // Attendance and logged hours for the past working days -----------------
        $tasks = Task::where('project_id', $clinic->id)->get();

        foreach (range(6, 1) as $daysAgo) {
            $day = now('Asia/Tehran')->subDays($daysAgo);

            if ($day->isFriday()) {
                continue;
            }

            foreach ([$ehsan, $sara, $ali, $maryam] as $i => $person) {
                $in = $day->copy()->setTime(8, 15 + $i * 10 + rand(0, 20))->utc();
                $out = $day->copy()->setTime(16, 30 + rand(0, 60))->utc();

                $presence = new Attendance([
                    'started_at' => $in, 'last_activity_at' => $out, 'ended_at' => $out,
                    'end_reason' => rand(0, 5) ? 'logout' : 'timeout', 'ip_address' => '192.168.1.'.(20 + $i),
                ]);
                $presence->user_id = $person->id;
                $presence->save();

                foreach (range(1, rand(2, 3)) as $n) {
                    $task = $tasks->random();
                    $start = $in->copy()->addMinutes(20 + ($n - 1) * 150);
                    $minutes = rand(40, 130);

                    $entry = new TimeEntry;
                    $entry->workspace_id = $dev->id;
                    $entry->task_id = $task->id;
                    $entry->project_id = $clinic->id;
                    $entry->user_id = $person->id;
                    $entry->started_at = $start;
                    $entry->ended_at = $start->copy()->addMinutes($minutes);
                    $entry->seconds = $minutes * 60;
                    $entry->save();
                }
            }
        }

        $main->forceFill(['estimate_minutes' => 20 * 60])->save();

        // Chat ------------------------------------------------------------------
        $direct = new Conversation(['type' => 'direct']);
        $direct->workspace_id = $dev->id;
        $direct->direct_key = Conversation::directKey($dev->id, $ehsan->id, $sara->id);
        $direct->created_by = $ehsan->id;
        $direct->last_message_at = now()->subMinutes(5);
        $direct->save();
        $direct->participants()->attach([$ehsan->id => ['role' => 'member', 'last_read_at' => now()->subHour()], $sara->id => ['role' => 'member', 'last_read_at' => now()]]);

        foreach ([[$ehsan, 'سلام سارا، طرح صفحه نوبت‌دهی به کجا رسید؟', 30], [$sara, 'سلام! وایرفریم تمام شد، امروز طرح نهایی رو می‌فرستم.', 20], [$sara, 'فقط محتوای صفحه خدمات رو لازم دارم 🙏', 5]] as [$who, $body, $mins]) {
            $m = new Message(['body' => $body]);
            $m->conversation_id = $direct->id;
            $m->user_id = $who->id;
            $m->created_at = now()->subMinutes($mins);
            $m->save();
        }

        $group = new Conversation(['type' => 'group', 'name' => 'تیم فنی']);
        $group->workspace_id = $dev->id;
        $group->created_by = $ehsan->id;
        $group->last_message_at = now()->subHours(2);
        $group->save();
        $group->participants()->attach([$ehsan->id => ['role' => 'admin'], $ali->id => ['role' => 'member'], $sara->id => ['role' => 'member']]);
        $m = new Message(['body' => 'دیپلوی نسخه جدید امشب ساعت ۱۰ انجام می‌شود.']);
        $m->conversation_id = $group->id;
        $m->user_id = $ali->id;
        $m->created_at = now()->subHours(2);
        $m->save();

        // Letters -------------------------------------------------------------------
        foreach ([[$maryam, 'درخواست پیش‌فاکتور برای کلینیک حامی', 'urgent'], [$sara, 'گزارش پیشرفت طراحی هفته دوم', 'normal']] as $i => [$from, $subject, $priority]) {
            $letter = new Letter(['subject' => $subject, 'body' => "با سلام و احترام،\n\nخواهشمند است موارد زیر بررسی و نتیجه اعلام گردد.\n\nبا تشکر", 'priority' => $priority]);
            $letter->workspace_id = $dev->id;
            $letter->sender_id = $from->id;
            $letter->number = $i + 1;
            $letter->created_at = now()->subHours(($i + 1) * 5);
            $letter->save();
            $letter->recipients()->attach([$ehsan->id => ['type' => 'to'], $ali->id => ['type' => 'cc']]);
        }

        // Meeting minutes (صورت‌جلسه) ---------------------------------------------
        $consultant = new Person(['name' => 'مهندس طاهری — مشاور زیرساخت']);
        $consultant->workspace_id = $dev->id;
        $consultant->created_by = $ehsan->id;
        $consultant->save();

        $review = new Task(['title' => 'نهایی کردن طرح صفحه نوبت‌دهی', 'priority' => 2, 'due_at' => now()->addDays(3)->setTime(12, 0)]);
        $review->workspace_id = $dev->id;
        $review->creator_id = $ehsan->id;
        $review->save();
        $review->assignees()->sync([$sara->id]);

        $minute = new Minute([
            'subject' => 'جلسه هم‌راستایی محصول — کلینیک حامی',
            'body' => "۱. وضعیت طراحی صفحات مرور شد؛ وایرفریم نوبت‌دهی تأیید و طرح نهایی تا پایان هفته تحویل می‌شود.\n۲. درگاه پرداخت آماده تست است؛ مستندات اتصال از طرف مشاور زیرساخت دریافت شود.\n۳. محتوای صفحات تا جلسه بعد توسط تیم فروش تدارک دیده شود.",
            'held_at' => now()->subDays(2)->setTime(11, 0),
            'location' => 'اتاق جلسه ۲',
            'status' => 'final',
        ]);
        $minute->workspace_id = $dev->id;
        $minute->organizer_id = $ehsan->id;
        $minute->created_by = $ehsan->id;
        $minute->number = 1;
        $minute->save();

        foreach ([[$ehsan, null, true], [$sara, null, true], [$ali, null, true], [$maryam, null, false], [null, $consultant, true]] as [$user, $person, $attended]) {
            $row = new MinuteParticipant(['attended' => $attended]);
            $row->minute_id = $minute->id;
            $row->user_id = $user?->id;
            $row->person_id = $person?->id;
            $row->save();
        }

        foreach ([
            ['نهایی کردن طرح صفحه نوبت‌دهی', $sara, null, now()->addDays(3), $review->id],
            ['دریافت مستندات اتصال درگاه', null, $consultant, now()->addDays(7), null],
            ['تدارک محتوای صفحات', $maryam, null, now()->addDays(5), null],
        ] as $i => [$title, $user, $person, $due, $taskId]) {
            $action = new MinuteAction(['title' => $title, 'due_at' => $due, 'position' => $i]);
            $action->minute_id = $minute->id;
            $action->user_id = $user?->id;
            $action->person_id = $person?->id;
            $action->task_id = $taskId;
            $action->save();
        }

        $minute->approvals()->sync([$ehsan->id => ['created_at' => now()->subDay()], $sara->id => ['created_at' => now()->subHours(20)]]);

        $draft = new Minute([
            'subject' => 'بررسی هزینه‌های سرور و میزبانی',
            'body' => 'مقایسه پلن‌های میزبانی و تصمیم درباره تمدید سرور فعلی در جلسه بعد.',
            'held_at' => now()->subDay()->setTime(15, 30),
            'location' => 'آنلاین — تماس تصویری',
            'status' => 'draft',
        ]);
        $draft->workspace_id = $dev->id;
        $draft->organizer_id = $ehsan->id;
        $draft->created_by = $ehsan->id;
        $draft->number = 2;
        $draft->save();

        foreach ([[$ehsan, true], [$reza, true], [$ali, false]] as [$user, $attended]) {
            $row = new MinuteParticipant(['attended' => $attended]);
            $row->minute_id = $draft->id;
            $row->user_id = $user->id;
            $row->save();
        }

        $open = new MinuteAction(['title' => 'استخراج گزارش مصرف منابع سرور', 'position' => 0, 'due_at' => now()->addDays(2)]);
        $open->minute_id = $draft->id;
        $open->save();

        // Notes ---------------------------------------------------------------------
        $note = new Note([
            'title' => 'کارهای اولیه',
            'body' => null,
            'items' => [
                ['id' => 'a1', 'text' => 'انتخاب تصویر پروفایل برای خودت', 'done' => false],
                ['id' => 'a2', 'text' => 'دعوت از همکارانت برای عضویت', 'done' => true],
                ['id' => 'a3', 'text' => 'ایجاد وظایف اولیه برای خودت و دوستانت', 'done' => false],
            ],
            'color' => 'emerald',
            'pinned' => true,
        ]);
        $note->workspace_id = $dev->id;
        $note->user_id = $ehsan->id;
        $note->save();

        $idea = new Note(['title' => 'ایده‌ها', 'body' => "داشبورد مشتری برای کلینیک\nیادآور پیامکی نوبت", 'color' => 'amber']);
        $idea->workspace_id = $dev->id;
        $idea->user_id = $ehsan->id;
        $idea->save();

        // Customers -----------------------------------------------------------------
        foreach ([
            ['company', 'کلینیک حامی', null, '09121234567', 'customer', 450000000],
            ['person', 'دکتر نادری', 'کلینیک حامی', '09351112233', 'negotiation', 120000000],
            ['company', 'فروشگاه آفتاب', null, '02188776655', 'lead', 80000000],
        ] as [$kind, $name, $company, $mobile, $stage, $value]) {
            $customer = new Customer(['kind' => $kind, 'name' => $name, 'company' => $company, 'mobile' => $mobile, 'stage' => $stage, 'deal_value' => $value, 'city' => 'تهران']);
            $customer->workspace_id = $dev->id;
            $customer->created_by = $maryam->id;
            $customer->owner_id = $maryam->id;
            $customer->save();
        }
    }
}
