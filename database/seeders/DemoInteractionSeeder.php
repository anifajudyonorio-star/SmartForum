<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoInteractionSeeder extends Seeder
{
    private const GROUP_NAME = 'Software Engineering Discussion 2026';

    public function run(): void
    {
        $users = $this->seedUsers();
        $group = $this->seedGroup($users[0]);
        $this->seedMemberships($group, $users);
        $topics = $this->seedTopics($group, $users);
        $this->seedPosts($topics, $users);
    }

    /**
     * @return list<User>
     */
    private function seedUsers(): array
    {
        $definitions = [
            ['Fname' => 'Alex', 'Lname' => 'Morgan', 'email' => 'demo1@smartforum.com', 'role' => 'student'],
            ['Fname' => 'Jordan', 'Lname' => 'Lee', 'email' => 'demo2@smartforum.com', 'role' => 'lecturer'],
            ['Fname' => 'Sam', 'Lname' => 'Patel', 'email' => 'demo3@smartforum.com', 'role' => 'student'],
            ['Fname' => 'Riley', 'Lname' => 'Chen', 'email' => 'demo4@smartforum.com', 'role' => 'student'],
            ['Fname' => 'Casey', 'Lname' => 'Okafor', 'email' => 'demo5@smartforum.com', 'role' => 'student'],
            ['Fname' => 'Taylor', 'Lname' => 'Nguyen', 'email' => 'demo6@smartforum.com', 'role' => 'student'],
            ['Fname' => 'Morgan', 'Lname' => 'Brooks', 'email' => 'demo7@smartforum.com', 'role' => 'student'],
            ['Fname' => 'Jamie', 'Lname' => 'Silva', 'email' => 'demo8@smartforum.com', 'role' => 'student'],
            ['Fname' => 'Drew', 'Lname' => 'Kim', 'email' => 'demo9@smartforum.com', 'role' => 'student'],
            ['Fname' => 'Quinn', 'Lname' => 'Hassan', 'email' => 'demo10@smartforum.com', 'role' => 'student'],
        ];

        $users = [];

        foreach ($definitions as $definition) {
            $users[] = User::updateOrCreate(
                ['email' => $definition['email']],
                array_merge($definition, ['password' => 'password'])
            );
        }

        return $users;
    }

    private function seedGroup(User $creator): Group
    {
        return Group::updateOrCreate(
            ['Group_Name' => self::GROUP_NAME],
            [
                'Description' => 'A demo study group for software engineering topics, assignments, and exam prep. Created for SmartForum presentation and testing.',
                'Created_By' => $creator->id,
                'Status' => 'Active',
            ]
        );
    }

    /**
     * @param  list<User>  $users
     */
    private function seedMemberships(Group $group, array $users): void
    {
        $roles = [
            GroupMember::ROLE_ADMIN,
            GroupMember::ROLE_LECTURER,
            GroupMember::ROLE_MEMBER,
            GroupMember::ROLE_MEMBER,
            GroupMember::ROLE_MEMBER,
            GroupMember::ROLE_MEMBER,
            GroupMember::ROLE_MEMBER,
            GroupMember::ROLE_MEMBER,
            GroupMember::ROLE_MEMBER,
            GroupMember::ROLE_MEMBER,
        ];

        foreach ($users as $index => $user) {
            $role = $roles[$index];

            if ($group->isMember($user->id)) {
                $group->members()->updateExistingPivot($user->id, [
                    'Member_Status' => GroupMember::STATUS_ACTIVE,
                    'Member_Role' => $role,
                    'warnings' => 0,
                ]);
            } else {
                $group->members()->attach($user->id, [
                    'Member_Status' => GroupMember::STATUS_ACTIVE,
                    'Member_Role' => $role,
                    'warnings' => 0,
                ]);
            }
        }
    }

    /**
     * @param  list<User>  $users
     * @return list<Topic>
     */
    private function seedTopics(Group $group, array $users): array
    {
        $definitions = [
            [
                'Title' => 'Welcome & Introductions',
                'Topic_Description' => 'Say hello, share your background, and tell us what you hope to get from this group.',
                'Created_By' => $users[0]->id,
            ],
            [
                'Title' => 'Assignment 1: REST API Design',
                'Topic_Description' => 'Discuss requirements, endpoints, and grading rubric for the first assignment.',
                'Created_By' => $users[1]->id,
            ],
            [
                'Title' => 'Database Normalization Help',
                'Topic_Description' => 'Ask questions about 1NF, 2NF, 3NF, and practical schema design examples.',
                'Created_By' => $users[2]->id,
            ],
            [
                'Title' => 'Midterm Study Group',
                'Topic_Description' => 'Coordinate revision sessions and share notes before the midterm exam.',
                'Created_By' => $users[4]->id,
            ],
            [
                'Title' => 'Agile vs Waterfall Debate',
                'Topic_Description' => 'Which methodology works better for student projects? Share experiences and opinions.',
                'Created_By' => $users[6]->id,
            ],
            [
                'Title' => 'Capstone Project Ideas',
                'Topic_Description' => 'Brainstorm final-year project ideas and find teammates with complementary skills.',
                'Created_By' => $users[8]->id,
            ],
        ];

        $topics = [];

        foreach ($definitions as $definition) {
            $topics[] = Topic::updateOrCreate(
                [
                    'Group_ID' => $group->id,
                    'Title' => $definition['Title'],
                ],
                [
                    'Topic_Description' => $definition['Topic_Description'],
                    'Created_By' => $definition['Created_By'],
                ]
            );
        }

        return $topics;
    }

    /**
     * @param  list<Topic>  $topics
     * @param  list<User>  $users
     */
    private function seedPosts(array $topics, array $users): void
    {
        if (Post::where('Topic_ID', $topics[0]->id)->exists()) {
            return;
        }

        $baseTime = Carbon::now()->subDays(14);
        $postIndex = 0;

        $conversations = [
            // Topic 0: Welcome
            [
                ['u' => 0, 'c' => 'Welcome everyone! I created this group so we can collaborate on SE coursework and share resources.'],
                ['u' => 1, 'c' => 'Great initiative, Alex. I will post assignment clarifications here when needed.', 'reply' => 0],
                ['u' => 2, 'c' => 'Hi all — Sam here, third-year CS. Looking forward to the discussions!'],
                ['u' => 3, 'c' => 'Riley checking in. Happy to help with debugging sessions.', 'reply' => 0],
                ['u' => 4, 'c' => 'Does anyone else struggle with time management between labs and assignments?', 'reply' => 0],
                ['u' => 5, 'c' => 'Same here. A shared calendar in this group might help.', 'reply' => 4],
                ['u' => 6, 'c' => 'I can set up a weekly stand-up thread if people want.'],
                ['u' => 7, 'c' => 'Count me in for stand-ups!', 'reply' => 6],
                ['u' => 8, 'c' => 'Welcome everyone — excited to be here.'],
                ['u' => 9, 'c' => 'Quinn here. I am especially interested in the capstone topic later.', 'reply' => 0],
            ],
            // Topic 1: REST API
            [
                ['u' => 1, 'c' => 'Assignment 1 is live. You must design a REST API for a library system with users, books, and loans. Due in two weeks.'],
                ['u' => 0, 'c' => 'Should we include authentication endpoints in the design doc?', 'reply' => 0],
                ['u' => 1, 'c' => 'Yes — document login, token refresh, and role-based access at minimum.', 'reply' => 1],
                ['u' => 3, 'c' => 'Are we allowed to use OpenAPI/Swagger for the submission?'],
                ['u' => 1, 'c' => 'Swagger is encouraged. Export the YAML with your PDF.', 'reply' => 3],
                ['u' => 4, 'c' => 'I am confused about pagination — cursor vs offset?'],
                ['u' => 2, 'c' => 'Offset is simpler for small datasets; cursor scales better for production.', 'reply' => 5],
                ['u' => 5, 'c' => 'Thanks Sam, that clears it up.', 'reply' => 6],
                ['u' => 7, 'c' => 'Can we work in pairs for this assignment?'],
                ['u' => 1, 'c' => 'Pairs are allowed but each student submits individually.', 'reply' => 8],
                ['u' => 9, 'c' => 'I will share my endpoint checklist in a Google Doc tonight.'],
            ],
            // Topic 2: Normalization
            [
                ['u' => 2, 'c' => 'Can someone explain why student address should not live in the enrollments table?'],
                ['u' => 6, 'c' => 'Because address depends on the student, not the enrollment relationship.', 'reply' => 0],
                ['u' => 0, 'c' => 'Think functional dependencies: enrollment_id -> course_id, not address.', 'reply' => 0],
                ['u' => 3, 'c' => 'What about storing course name redundantly for reporting speed?'],
                ['u' => 1, 'c' => 'Denormalize only when you measure a real performance need.', 'reply' => 3],
                ['u' => 4, 'c' => 'This helped a lot — my schema finally passes 3NF.'],
                ['u' => 8, 'c' => 'Same question for composite keys in junction tables?', 'reply' => 0],
                ['u' => 2, 'c' => 'Use a surrogate id plus unique constraint on the pair.', 'reply' => 6],
            ],
            // Topic 3: Midterm
            [
                ['u' => 4, 'c' => 'Midterm is in 10 days. Who wants a Saturday review session on campus?'],
                ['u' => 5, 'c' => 'Saturday works for me after 2 PM.', 'reply' => 0],
                ['u' => 7, 'c' => 'I can bring past paper summaries.', 'reply' => 0],
                ['u' => 0, 'c' => 'Room 204 is free 3–5 PM if we book it today.', 'reply' => 0],
                ['u' => 9, 'c' => 'Can we cover UML class diagrams? That section always trips me up.'],
                ['u' => 1, 'c' => 'Yes — I will prepare a 20-minute UML recap for the session.', 'reply' => 4],
                ['u' => 3, 'c' => 'Should we focus on chapters 1–6 only?'],
                ['u' => 1, 'c' => 'Correct. Chapter 7 is for the final exam.', 'reply' => 6],
                ['u' => 6, 'c' => 'I will create a shared quiz on the topics we miss.'],
            ],
            // Topic 4: Agile vs Waterfall
            [
                ['u' => 6, 'c' => 'For our group project, I think Agile fits better because requirements change every sprint.'],
                ['u' => 8, 'c' => 'Waterfall gives clearer milestones though — easier to grade.', 'reply' => 0],
                ['u' => 0, 'c' => 'Hybrid might work: waterfall planning, agile delivery.', 'reply' => 0],
                ['u' => 2, 'c' => 'We used Scrum last semester and daily stand-ups actually helped.', 'reply' => 0],
                ['u' => 5, 'c' => 'Stand-ups feel overkill for a three-person team though.', 'reply' => 3],
                ['u' => 6, 'c' => 'Fair point — maybe weekly check-ins instead.', 'reply' => 4],
                ['u' => 1, 'c' => 'Document whichever process you choose in your project report.'],
                ['u' => 9, 'c' => 'Has anyone tried Kanban boards for coursework?'],
                ['u' => 4, 'c' => 'Trello works great for visualizing assignment backlog.', 'reply' => 7],
            ],
            // Topic 5: Capstone
            [
                ['u' => 8, 'c' => 'I am thinking of a smart campus navigation app with indoor maps. Need a backend developer.'],
                ['u' => 3, 'c' => 'I can handle the API and database layer.', 'reply' => 0],
                ['u' => 7, 'c' => 'I do mobile UI — happy to join if you need Flutter.', 'reply' => 0],
                ['u' => 0, 'c' => 'Consider integrating with existing timetable data for class locations.', 'reply' => 0],
                ['u' => 9, 'c' => 'Another idea: forum analytics dashboard for lecturers using engagement metrics.'],
                ['u' => 1, 'c' => 'Both ideas are viable — scope carefully and define MVP features early.', 'reply' => 4],
                ['u' => 2, 'c' => 'Our team wants to build an offline-first quiz app for rural schools.'],
                ['u' => 5, 'c' => 'That aligns with the accessibility theme this year.', 'reply' => 6],
                ['u' => 6, 'c' => 'Anyone interested in ML-based topic recommendations for study groups?'],
                ['u' => 4, 'c' => 'That sounds like SmartForum itself — meta but cool!', 'reply' => 8],
            ],
        ];

        $createdPosts = [];

        foreach ($conversations as $topicIndex => $messages) {
            $topic = $topics[$topicIndex];

            foreach ($messages as $message) {
                $parentId = null;

                if (isset($message['reply'])) {
                    $parentId = $createdPosts[$topicIndex][$message['reply']]->id ?? null;
                }

                $createdAt = $baseTime->copy()->addHours($postIndex);
                $postIndex++;

                $post = Post::create([
                    'Topic_ID' => $topic->id,
                    'Parent_Post_ID' => $parentId,
                    'Created_By' => $users[$message['u']]->id,
                    'Post_Content' => $message['c'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $createdPosts[$topicIndex][] = $post;
            }
        }
    }
}
