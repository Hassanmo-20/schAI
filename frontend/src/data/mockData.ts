import { AcademicTask, AppNotification, Batch, RegistrationOptions, User } from '../types';

/**
 * Mirrors the groups created by the Laravel SchAIDemoSeeder: every
 * (batch year, department) pair the backend enums offer.
 */
export const MOCK_BATCHES: Batch[] = ['2027', '2028', '2028++', '2029', '2030'].flatMap(
  (batchYear, yearIndex) =>
    ['CCE', 'CSE'].map((department, deptIndex) => ({
      id: String(yearIndex * 2 + deptIndex + 1),
      name: `${batchYear} ${department}`,
      batchYear,
      department,
    }))
);

/** Mirrors `GET /api/registration-options`. */
export const MOCK_REGISTRATION_OPTIONS: RegistrationOptions = {
  batchYears: ['2027', '2028', '2028++', '2029', '2030'].map((v) => ({ value: v, label: v })),
  departments: [
    { value: 'CCE', label: 'Computer & Communication Engineering' },
    { value: 'CSE', label: 'Computer Science & Engineering' },
  ],
  roles: [
    { value: 'student', label: 'Student' },
    { value: 'representative', label: 'Batch Representative' },
  ],
};

export const MOCK_USERS: Record<string, User> = {
  student: {
    id: 'usr_student_01',
    name: 'Alex Mercer',
    email: 'student@schai.test',
    role: 'student',
    batch: '2027 CCE',
    batchYear: '2027',
    department: 'CCE',
    avatarUrl: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=60'
  },
  representative: {
    id: 'usr_rep_01',
    name: 'Sarah Connor',
    email: 'representative@schai.test',
    role: 'representative',
    batch: '2027 CCE',
    batchYear: '2027',
    department: 'CCE',
    avatarUrl: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=60'
  }
};

const getRelativeIso = (hoursFromNow: number): string => {
  const d = new Date();
  d.setHours(d.getHours() + hoursFromNow);
  return d.toISOString();
};

export const INITIAL_MOCK_TASKS: AcademicTask[] = [
  {
    id: 'task_01',
    title: 'Database Assignment 2: Relational Normalization & SQL Queries',
    description: 'Deconstruct unnormalized database tables to 3NF/BCNF. Write SQL query scripts for complex joins, window functions, and indexing strategies. Submit your answers in a formatted report alongside the sql script.',
    type: 'Assignment',
    deadline: getRelativeIso(6), // Due Tonight
    batch: '2027 CCE',
    createdBy: 'usr_rep_01',
    createdByName: 'Sarah Connor',
    isCompleted: false,
    totalStudents: 50,
    completedStudents: 38, // 76%
    attachments: [
      {
        id: 'att_01',
        name: 'database_schema_diagram.png',
        url: 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=800&auto=format&fit=crop&q=80',
        type: 'image',
        size: '1.2 MB'
      },
      {
        id: 'att_02',
        name: 'normalization_rubric.pdf',
        url: 'https://example.com/rubric.pdf',
        type: 'pdf',
        size: '340 KB'
      }
    ]
  },
  {
    id: 'task_02',
    title: 'OOP Quiz: Polymorphism, Virtual Functions & Templates',
    description: 'Online timed quiz covering Object-Oriented Programming principles, abstract classes, operator overloading, and generic templates in C++. You will have 45 minutes once started.',
    type: 'Quiz',
    deadline: getRelativeIso(26), // Due Tomorrow
    batch: '2027 CCE',
    createdBy: 'usr_rep_01',
    createdByName: 'Sarah Connor',
    isCompleted: false,
    totalStudents: 50,
    completedStudents: 12, // 24%
    attachments: []
  },
  {
    id: 'task_03',
    title: 'Midterm Exam: Algorithms & Data Structures',
    description: 'Comprehensive physical midterm exam held in Auditorium B. Topics include asymptotic analysis, heaps, balanced binary search trees, graph traversals, and dynamic programming.',
    type: 'Midterm',
    deadline: getRelativeIso(24 * 7), // 7 days
    batch: '2027 CCE',
    createdBy: 'usr_rep_01',
    createdByName: 'Sarah Connor',
    isCompleted: false,
    totalStudents: 50,
    completedStudents: 0, // 0%
    attachments: [
      {
        id: 'att_03',
        name: 'algorithms_midterm_guidelines.pdf',
        url: 'https://example.com/midterm_guidelines.pdf',
        type: 'pdf',
        size: '520 KB'
      }
    ]
  },
  {
    id: 'task_04',
    title: 'Programming Assignment: Distributed Cache Key-Value Store',
    description: 'Build a distributed key-value cache with consistent hashing, LRU eviction, and replica failover simulation. Thorough unit tests are required for cluster node synchronization.',
    type: 'Assignment',
    deadline: getRelativeIso(48), // 2 days
    batch: '2027 CCE',
    createdBy: 'usr_rep_01',
    createdByName: 'Sarah Connor',
    isCompleted: true,
    completedAt: new Date(Date.now() - 3600000 * 4).toISOString(),
    totalStudents: 50,
    completedStudents: 45, // 90%
    attachments: [
      {
        id: 'att_04',
        name: 'cache_architecture_specs.png',
        url: 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=800&auto=format&fit=crop&q=80',
        type: 'image',
        size: '890 KB'
      }
    ]
  },
  {
    id: 'task_05',
    title: 'Old Assignment: Computer Architecture MIPS Pipeline Simulation',
    description: 'Implementation of standard 5-stage MIPS pipeline with forwarding unit and hazard detection. Submission deadline has passed. Late submissions incur a 10% daily penalty.',
    type: 'Assignment',
    deadline: getRelativeIso(-24), // Yesterday (Overdue)
    batch: '2027 CCE',
    createdBy: 'usr_rep_01',
    createdByName: 'Sarah Connor',
    isCompleted: false,
    totalStudents: 50,
    completedStudents: 48, // 96%
    attachments: []
  },
  {
    id: 'task_06',
    title: 'Software Engineering Capstone Milestone 1',
    description: 'Submit requirements specification document (SRS), high-fidelity wireframes, and architectural diagrams for the semester project.',
    type: 'Project',
    deadline: getRelativeIso(24 * 14), // 14 days
    batch: '2027 CCE',
    createdBy: 'usr_rep_01',
    createdByName: 'Sarah Connor',
    isCompleted: false,
    totalStudents: 50,
    completedStudents: 15,
    attachments: [
      {
        id: 'att_05',
        name: 'srs_template_standard.pdf',
        url: 'https://example.com/srs.pdf',
        type: 'pdf',
        size: '410 KB'
      }
    ]
  },
  {
    id: 'task_07',
    title: 'Computer Networks Lab 3: Wireshark Packet Inspection',
    description: 'Capture TCP handshake packets and analyze sliding window flow control. Answer the questionnaire provided in the lab portal.',
    type: 'Assignment',
    deadline: getRelativeIso(24 * 4), // 4 days
    batch: '2027 CCE',
    createdBy: 'usr_rep_01',
    createdByName: 'Sarah Connor',
    isCompleted: false,
    totalStudents: 50,
    completedStudents: 25, // 50%
    attachments: []
  },
  {
    id: 'task_08',
    title: 'Final Examination: Operating Systems & Concurrency',
    description: 'Cumulative final exam covering virtual memory management, page replacement algorithms, mutexes, condition variables, and deadlock avoidance.',
    type: 'Exam',
    deadline: getRelativeIso(24 * 30), // 30 days
    batch: '2027 CCE',
    createdBy: 'usr_rep_01',
    createdByName: 'Sarah Connor',
    isCompleted: false,
    totalStudents: 50,
    completedStudents: 1, // 2%
    attachments: []
  }
];

/**
 * Mirrors the three backend notification classes (App\Notifications): a new
 * task posted for the group, a change to an existing one, and the hourly
 * deadline reminder.
 */
export const MOCK_NOTIFICATIONS: AppNotification[] = [
  {
    id: 'ntf_01',
    type: 'deadline_approaching',
    title: 'Assignment due in 6 hours',
    message: '"Database Assignment 2: Relational Normalization & SQL Queries" is due soon.',
    taskId: 'task_01',
    taskTitle: 'Database Assignment 2: Relational Normalization & SQL Queries',
    deadline: getRelativeIso(6),
    isRead: false,
    createdAt: getRelativeIso(-1),
  },
  {
    id: 'ntf_02',
    type: 'task_published',
    title: 'New Quiz posted',
    message: '"OOP Quiz: Polymorphism, Virtual Functions & Templates" is due tomorrow.',
    taskId: 'task_02',
    taskTitle: 'OOP Quiz: Polymorphism, Virtual Functions & Templates',
    deadline: getRelativeIso(26),
    isRead: false,
    createdAt: getRelativeIso(-20),
  },
  {
    id: 'ntf_03',
    type: 'task_updated',
    title: 'Midterm deadline changed',
    message: '"Midterm Exam: Algorithms & Data Structures" now has a new deadline.',
    taskId: 'task_03',
    taskTitle: 'Midterm Exam: Algorithms & Data Structures',
    deadline: getRelativeIso(24 * 7),
    isRead: true,
    readAt: getRelativeIso(-30),
    createdAt: getRelativeIso(-48),
  },
];
