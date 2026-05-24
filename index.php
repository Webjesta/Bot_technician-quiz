<?php
/**
 * Bank IT Technician Selection Exam
 * Designed & Engineered by pume (Tabula Tiff Engine)
 * Fully responsive single-file PHP secure testing suite with persistent progression tracking.
 */

// ==========================================
// 1. DATABASE CONFIGURATION (pume configuration)
// ==========================================
$pume_host = 'localhost';
$pume_user = 'root';
$pume_pass = '';
$pume_name = 'bot';

try {
    // Establishing secure pume database connector
    $pume_pdo = new PDO("mysql:host=$pume_host;dbname=$pume_name;charset=utf8mb4", $pume_user, $pume_pass);
    $pume_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

// ==========================================
// 2. BACKEND API LOGIC (tabula_tiff Handler)
// ==========================================
$pume_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pume_action = $_GET['action'] ?? '';

if ($pume_method === 'POST') {
    header('Content-Type: application/json');
    $pume_input = json_decode(file_get_contents('php://input'), true);

    // ACTION: Fetch Questions
    if ($pume_action === 'fetch_questions') {
        try {
            // Fetch 50 random questions. 
            // SECURITY: Do NOT select 'correct_answer' to prevent browser inspection.
            $pume_stmt = $pume_pdo->prepare("SELECT id, category, question, option_a, option_b, option_c, option_d FROM questions ORDER BY RAND() LIMIT 50");
            $pume_stmt->execute();
            $questions = $pume_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($questions) == 0) {
                echo json_encode(['status' => 'error', 'message' => 'No questions found in the database.']);
                exit;
            }

            echo json_encode(['status' => 'success', 'data' => $questions]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    // ACTION: Fetch Candidate Progress History (tabula_tiff progression engine)
    if ($pume_action === 'fetch_history') {
        $candidate_name = $pume_input['candidate_name'] ?? '';
        if (empty($candidate_name)) {
            echo json_encode(['status' => 'success', 'history' => []]);
            exit;
        }

        try {
            $history_stmt = $pume_pdo->prepare("SELECT id, score, total_questions, percentage, grade, time_taken_seconds, created_at FROM exam_results WHERE candidate_name = ? ORDER BY created_at DESC");
            $history_stmt->execute([$candidate_name]);
            $history_records = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'history' => $history_records]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error fetching user log: ' . $e->getMessage()]);
            exit;
        }
    }

    // ACTION: Submit Exam
    if ($pume_action === 'submit_exam') {
        $candidate_name = $pume_input['candidate_name'] ?? 'Unknown Candidate';
        $time_taken = (int)($pume_input['time_taken'] ?? 0);
        $user_answers = $pume_input['answers'] ?? []; // format: ['question_id' => 'selected_option_index']

        try {
            $tabula_tiff_score = 0;
            $total_questions = 50;
            
            if (!empty($user_answers)) {
                $question_ids = array_keys($user_answers);
                $inQuery = implode(',', array_map('intval', $question_ids));
                
                $verify_stmt = $pume_pdo->prepare("SELECT id, correct_answer FROM questions WHERE id IN ($inQuery)");
                $verify_stmt->execute();
                $correct_answers_db = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);

                $db_lookup = [];
                foreach ($correct_answers_db as $row) {
                    $db_lookup[$row['id']] = $row['correct_answer'];
                }

                // Verify choices against the verified database solution
                foreach ($user_answers as $q_id => $selected_opt) {
                    if (isset($db_lookup[$q_id]) && (int)$db_lookup[$q_id] === (int)$selected_opt) {
                        $tabula_tiff_score++;
                    }
                }
            }

            // Calculate Metrics
            $tabula_tiff_percentage = ($total_questions > 0) ? ($tabula_tiff_score / $total_questions) * 100 : 0;
            $tabula_tiff_grade = 'F';
            if ($tabula_tiff_percentage >= 80) $tabula_tiff_grade = 'A';
            elseif ($tabula_tiff_percentage >= 70) $tabula_tiff_grade = 'B';
            elseif ($tabula_tiff_percentage >= 60) $tabula_tiff_grade = 'C';
            elseif ($tabula_tiff_percentage >= 50) $tabula_tiff_grade = 'D';

            // Safe Database Logging
            $insert_stmt = $pume_pdo->prepare("INSERT INTO exam_results (candidate_name, score, total_questions, percentage, grade, time_taken_seconds, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->execute([
                $candidate_name,
                $tabula_tiff_score,
                $total_questions,
                $tabula_tiff_percentage,
                $tabula_tiff_grade,
                $time_taken,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'score' => $tabula_tiff_score,
                    'total_questions' => $total_questions,
                    'percentage' => round($tabula_tiff_percentage, 2),
                    'grade' => $tabula_tiff_grade,
                    'time_taken' => $time_taken
                ]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save results: ' . $e->getMessage()]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank IT Technician Selection Exam</title>
    <!-- Tailwind CSS & Google Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        bank: { 950: '#020617', 900: '#0f172a', 800: '#1e293b', 700: '#334155', blue: '#0284c7', light: '#f0f9ff' }
                    }
                }
            }
        }
    </script>
    <style>
        /* Premium styling by pume */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        
        .prevent-select { -webkit-user-select: none; -ms-user-select: none; user-select: none; }
        .q-card { scroll-margin-top: 100px; transition: all 0.25s ease; }
        
        /* Interactive inputs */
        input[type="radio"]:checked + div { 
            background-color: #f0f9ff; 
            border-color: #0284c7; 
            box-shadow: 0 0 0 1px #0284c7;
        }
        input[type="radio"]:checked + div span.radio-dot { background-color: #0284c7; border-color: #0284c7; }
        input[type="radio"]:checked + div span.radio-text { font-weight: 600; color: #0369a1; }
        
        /* Shimmer effect for loaders */
        .shimmer {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 font-sans min-h-screen flex flex-col selection:bg-bank-light selection:text-bank-950">

    <!-- Premium Header -->
    <header class="bg-gradient-to-r from-bank-950 to-bank-800 text-white py-4 px-6 shadow-xl sticky top-0 z-50 transition-all">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-gradient-to-br from-sky-400 to-blue-600 p-2.5 rounded-xl shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">Core IT Selection Protocol</h1>
                    <p class="text-[10px] md:text-xs text-sky-400 font-bold tracking-widest uppercase">Tabula Tiff Engine V2</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div id="header-status" class="text-xs md:text-sm font-semibold bg-white/10 px-4 py-2 rounded-full border border-white/10 shadow-inner transition-all">
                    Authentication Required
                </div>
                <button id="btn-signout" onclick="pumeSignOut()" class="hidden fade-in text-xs font-bold bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 px-4 py-2 rounded-full transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                    Sign Out
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow w-full max-w-7xl mx-auto p-4 md:p-6 flex flex-col items-center justify-center relative">
        
        <!-- INTERACTIVE EVALUATING LOADING OVERLAY -->
        <div id="evaluating-overlay" class="hidden fixed inset-0 z-[60] bg-bank-950/80 backdrop-blur-md flex flex-col items-center justify-center">
            <div class="bg-white p-8 md:p-12 rounded-2xl shadow-2xl flex flex-col items-center text-center max-w-md w-full border border-slate-100 mx-4">
                <div class="relative w-20 h-20 mb-6">
                    <!-- Spinning main loader -->
                    <div class="absolute inset-0 rounded-full border-4 border-slate-100"></div>
                    <div class="absolute inset-0 rounded-full border-4 border-bank-blue border-t-transparent animate-spin"></div>
                    <div class="absolute inset-4 rounded-full bg-bank-light flex items-center justify-center">
                        <svg class="w-6 h-6 text-bank-blue animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-1">Evaluating Performance</h3>
                <p id="evaluating-subtitle" class="text-xs text-sky-500 font-bold uppercase tracking-widest mb-4">Securing payload</p>
                
                <!-- Detailed interactive checks -->
                <div class="w-full space-y-2.5 mt-2 bg-slate-50 p-4 rounded-xl border border-slate-100 text-left">
                    <div id="step-0" class="flex items-center gap-3 text-sm font-semibold text-slate-400">
                        <span id="step-icon-0" class="w-4 h-4 rounded-full border border-slate-200 flex items-center justify-center text-[10px]">1</span>
                        <span id="step-text-0">Decrypting response payload...</span>
                    </div>
                    <div id="step-1" class="flex items-center gap-3 text-sm font-semibold text-slate-400">
                        <span id="step-icon-1" class="w-4 h-4 rounded-full border border-slate-200 flex items-center justify-center text-[10px]">2</span>
                        <span id="step-text-1">Analyzing answers server-side...</span>
                    </div>
                    <div id="step-2" class="flex items-center gap-3 text-sm font-semibold text-slate-400">
                        <span id="step-icon-2" class="w-4 h-4 rounded-full border border-slate-200 flex items-center justify-center text-[10px]">3</span>
                        <span id="step-text-2">Calculating weighted precision...</span>
                    </div>
                    <div id="step-3" class="flex items-center gap-3 text-sm font-semibold text-slate-400">
                        <span id="step-icon-3" class="w-4 h-4 rounded-full border border-slate-200 flex items-center justify-center text-[10px]">4</span>
                        <span id="step-text-3">Writing secure database logs...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- LOADING OVERLAY -->
        <div id="loading-overlay" class="hidden fixed inset-0 z-[60] bg-slate-900/40 backdrop-blur-sm flex flex-col items-center justify-center">
            <div class="bg-white p-8 rounded-2xl shadow-2xl flex flex-col items-center text-center max-w-sm w-full mx-4">
                <svg class="animate-spin h-10 w-10 text-bank-blue mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p id="loading-text" class="text-md font-semibold text-slate-700">Loading secure environment...</p>
            </div>
        </div>

        <!-- VIEW 1: Registration / Login -->
        <div id="view-login" class="fade-in bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden max-w-4xl w-full flex flex-col md:flex-row">
            <!-- Left Branding Side -->
            <div class="bg-bank-900 p-8 md:p-12 text-white md:w-2/5 flex flex-col justify-center relative overflow-hidden">
                <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-white via-transparent to-transparent"></div>
                <h2 class="text-3xl font-extrabold mb-4 relative z-10 tracking-tight">IT Department</h2>
                <p class="text-sky-200 text-sm leading-relaxed relative z-10">Secure vetting system for aspiring Banking IT Support Technicians. Designed to evaluate operational excellence under standard pressure environments.</p>
                
                <div class="mt-8 space-y-4 relative z-10">
                    <div class="flex items-center gap-3.5 text-sm font-medium text-slate-300">
                        <div class="p-1.5 bg-white/10 rounded-lg"><svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                        <span>25 Minutes Duration</span>
                    </div>
                    <div class="flex items-center gap-3.5 text-sm font-medium text-slate-300">
                        <div class="p-1.5 bg-white/10 rounded-lg"><svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg></div>
                        <span>50 Technical Questions</span>
                    </div>
                </div>
            </div>
            
            <!-- Right Form Side -->
            <div class="p-8 md:p-12 md:w-3/5 flex flex-col justify-center bg-white">
                <div class="mb-6">
                    <h3 class="text-2xl font-black text-slate-800 tracking-tight mb-2">Security Verification</h3>
                    <p class="text-slate-500 text-sm">Please input your legal name. Previous progress and attempts will be automatically synchronized with your identity.</p>
                </div>
                
                <form id="form-login" class="space-y-6">
                    <div>
                        <label for="candidate_name" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Full Legal Name</label>
                        <div class="relative">
                            <input type="text" id="candidate_name" required placeholder="e.g. John Doe" class="w-full pl-12 pr-4 py-4 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-bank-blue focus:border-bank-blue transition-all outline-none text-slate-800 font-semibold" oninput="checkInputHistory()">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <!-- Dynamic Progression Button (Appears if user matches history) -->
                    <div id="history-banner" class="hidden fade-in bg-slate-50 border border-slate-100 rounded-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="flex items-center gap-3 text-left">
                            <div class="p-2 bg-blue-100 rounded-lg text-bank-blue">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Progression Log</h4>
                                <p class="text-sm font-semibold text-slate-700">Previous scores were found on your name.</p>
                            </div>
                        </div>
                        <button type="button" onclick="openHistoryModal()" class="w-full sm:w-auto px-4 py-2 bg-white hover:bg-slate-100 border border-slate-200 text-bank-blue font-bold rounded-lg text-xs transition-colors flex items-center justify-center gap-1 shadow-sm">
                            View Progress
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>

                    <button type="submit" class="w-full bg-bank-blue hover:bg-sky-600 text-white font-bold py-4 px-6 rounded-xl transition-all shadow-lg shadow-sky-200 flex justify-center items-center gap-2 group">
                        Authenticate & Connect
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform group-hover:translate-x-1 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <!-- VIEW 2: Readiness & Instructions -->
        <div id="view-ready" class="hidden fade-in bg-white rounded-2xl shadow-xl border border-slate-100 p-8 md:p-12 w-full max-w-4xl">
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-sky-50 text-bank-blue mb-4 shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h2 class="text-3xl font-black text-slate-800 tracking-tight mb-2">Dashboard: <span id="display_name" class="text-bank-blue">Candidate</span></h2>
                <p class="text-slate-500">Please review the official operational examination standards.</p>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6 mb-10">
                <div class="bg-slate-50 rounded-xl p-6 border border-slate-100 flex gap-4">
                    <div class="mt-1 text-bank-blue">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    </div>
                    <div>
                        <strong class="block text-slate-800 font-bold mb-1">50 Professional Questions</strong>
                        <p class="text-sm text-slate-500">Includes system updates, printer installations, security assessments, hardware diagnosis, and infrastructure setup.</p>
                    </div>
                </div>
                <div class="bg-slate-50 rounded-xl p-6 border border-slate-100 flex gap-4">
                    <div class="mt-1 text-red-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <strong class="block text-slate-800 font-bold mb-1">25-Minute Limit</strong>
                        <p class="text-sm text-slate-500">Strictly 1500 seconds countdown. If expired, our security layers auto-submit and log your answered matrix.</p>
                    </div>
                </div>
                <div class="bg-slate-50 rounded-xl p-6 border border-slate-100 flex gap-4">
                    <div class="mt-1 text-amber-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <strong class="block text-slate-800 font-bold mb-1">State Persistence Protection</strong>
                        <p class="text-sm text-slate-500">If you accidentally refresh the page, your progress, timer, and active session will be securely restored exactly as left.</p>
                    </div>
                </div>
                <div class="bg-slate-50 rounded-xl p-6 border border-slate-100 flex gap-4">
                    <div class="mt-1 text-emerald-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <strong class="block text-slate-800 font-bold mb-1">Automatic Analysis</strong>
                        <p class="text-sm text-slate-500">Results are audited immediately with percentage score and alphabetical grade (A to F) calculations.</p>
                    </div>
                </div>
            </div>

            <!-- Pre-start Checkbox Interactivity -->
            <div class="mb-8 p-4 bg-sky-50 border border-sky-100 rounded-xl flex items-center gap-3">
                <input type="checkbox" id="readiness-check" class="w-5 h-5 text-bank-blue rounded border-slate-300 focus:ring-bank-blue cursor-pointer" onchange="toggleStartBtn()">
                <label for="readiness-check" class="text-sm font-semibold text-slate-700 select-none cursor-pointer">I verify that I am prepared, clear of distractions, and ready to start the secure countdown.</label>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4 border-t border-slate-100">
                <button onclick="loadCandidateHistoryOnSuccess()" class="w-full sm:w-auto px-6 py-4 rounded-xl border border-slate-300 text-bank-blue font-bold hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" /></svg>
                    View Previous Logs
                </button>
                <button id="start-exam-btn" disabled onclick="fetchQuestionsAndStart()" class="w-full sm:w-auto px-10 py-4 rounded-xl bg-slate-300 text-slate-500 cursor-not-allowed font-bold transition-all flex items-center justify-center gap-2 shadow-sm">
                    Commence Assessment
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd" /></svg>
                </button>
            </div>
        </div>

        <!-- VIEW 3: The Exam Environment -->
        <div id="view-exam" class="hidden fade-in w-full transition-all duration-300">
            
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 relative">
                
                <!-- Left/Main: Questions Area -->
                <div class="lg:col-span-3 space-y-6 pb-12 prevent-select">
                    <!-- Progress Bar -->
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 mb-2">
                        <div class="flex justify-between items-center text-sm font-bold text-slate-700 mb-2.5">
                            <span class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-bank-blue animate-pulse"></span>
                                Core IT Questions
                            </span>
                            <span id="progress-text" class="text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-full border border-slate-200">0% Completed</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden border border-slate-200">
                            <div id="progress-bar-fill" class="bg-gradient-to-r from-bank-blue to-sky-400 h-full rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
                        </div>
                    </div>

                    <div id="questions-container" class="space-y-6">
                        <!-- JavaScript will inject questions here -->
                    </div>
                </div>

                <!-- Right: Control Panel (Sticky) -->
                <div class="lg:col-span-1">
                    <div class="sticky top-24 space-y-4">
                        
                        <!-- Timer Card -->
                        <div id="timer-card" class="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 text-center transform transition-all duration-300">
                            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Secure Timer</h3>
                            <div class="text-4xl font-mono font-black text-slate-800 tracking-tight" id="time-display">25:00</div>
                            <div class="w-full bg-slate-100 rounded-full h-2 mt-4 overflow-hidden border border-slate-200">
                                <div id="time-bar-fill" class="bg-bank-blue h-full rounded-full transition-all linear" style="width: 100%"></div>
                            </div>
                        </div>

                        <!-- Question Navigator -->
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 hidden md:block">
                            <h3 class="text-sm font-extrabold text-slate-800 mb-4 flex justify-between items-center border-b border-slate-100 pb-2">
                                Nav Matrix
                                <span class="text-xs bg-blue-50 text-bank-blue px-2.5 py-1 rounded-md border border-blue-100"><span id="answered-count">0</span> / 50</span>
                            </h3>
                            <div id="question-nav-grid" class="grid grid-cols-5 gap-2 max-h-[220px] overflow-y-auto pr-1">
                                <!-- JS will populate 50 small buttons here -->
                            </div>
                            <div class="flex justify-between items-center mt-4 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-50 border border-slate-200"></span> Open</div>
                                <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-bank-blue"></span> Active</div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button onclick="confirmSubmit()" class="w-full bg-bank-900 hover:bg-slate-800 text-white font-extrabold py-4 rounded-xl shadow-lg transition-colors flex justify-center items-center gap-2 hover:shadow-xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            Secure Submission
                        </button>

                    </div>
                </div>
            </div>
        </div>

        <!-- VIEW 4: Results Screen -->
        <div id="view-result" class="hidden fade-in bg-white rounded-2xl shadow-2xl border border-slate-100 p-8 md:p-12 max-w-3xl mx-auto w-full text-center relative overflow-hidden">
            <!-- Professional Blueprint header -->
            <div class="absolute top-0 left-0 w-full h-32 bg-slate-50 border-b border-slate-100 z-0"></div>
            
            <div class="relative z-10">
                <div id="result-icon" class="w-24 h-24 mx-auto bg-white border-4 border-white shadow-xl rounded-full flex items-center justify-center mb-6">
                    <!-- Icon injected by JS based on grade -->
                </div>
                <h2 class="text-3xl font-black text-slate-800 tracking-tight mb-1">Assessment Evaluation Report</h2>
                <p class="text-slate-500 mb-8 font-semibold tracking-wide" id="result-candidate-name"></p>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 text-left">
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Raw Score</div>
                        <div class="text-3xl font-black text-slate-800"><span id="res-score">0</span><span class="text-sm text-slate-400">/50</span></div>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Precision Rate</div>
                        <div class="text-3xl font-black text-bank-blue"><span id="res-perc">0</span>%</div>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Final Designation</div>
                        <div class="text-3xl font-black text-slate-800" id="res-grade">-</div>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                        <div class="text-[10px] text-slate-400 font-black uppercase tracking-widest mb-1">Time Expended</div>
                        <div class="text-xl font-bold text-slate-700 mt-2.5" id="res-time">0m 0s</div>
                    </div>
                </div>

                <div id="result-message" class="text-md font-bold text-slate-700 py-5 px-6 rounded-2xl border transition-all duration-300">
                    <!-- Conditional message based on pass/fail -->
                </div>

                <!-- TRY AGAIN AND PROGRESSION DASHBOARD ROW -->
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4 mt-8 pt-6 border-t border-slate-100">
                    <button onclick="restartExamSuite()" class="w-full sm:w-auto px-8 py-3.5 bg-bank-900 hover:bg-slate-800 text-white font-extrabold rounded-xl shadow-md transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H17" />
                        </svg>
                        Try Again (New Random Exam)
                    </button>
                    <button onclick="loadCandidateHistoryOnSuccess()" class="w-full sm:w-auto px-6 py-3.5 bg-white border border-slate-200 text-bank-blue hover:bg-slate-50 font-extrabold rounded-xl transition-colors flex items-center justify-center gap-1 shadow-sm">
                        View Complete Performance Logs
                    </button>
                </div>
            </div>
        </div>

    </main>

    <!-- Unified Progression Ledger Modal (tabula_tiff Progress Track) -->
    <div id="history-modal" class="hidden fixed inset-0 z-[100] bg-bank-950/60 backdrop-blur-sm flex items-center justify-center px-4 fade-in">
        <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full p-6 md:p-8 transform transition-all border border-slate-100">
            <div class="flex justify-between items-center border-b border-slate-100 pb-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-blue-50 rounded-xl text-bank-blue border border-blue-100 shadow-sm">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-slate-800 leading-tight tracking-tight">Progression Matrix Log</h3>
                        <p class="text-xs font-semibold text-slate-400 mt-0.5">Authentic profile mapping & past scores</p>
                    </div>
                </div>
                <button onclick="closeHistoryModal()" class="text-slate-400 hover:text-red-500 bg-slate-50 hover:bg-red-50 rounded-full p-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <!-- Progression Table -->
            <div class="overflow-x-auto rounded-xl border border-slate-200 max-h-[350px] bg-white">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 bg-slate-50 z-10">
                        <tr class="border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="p-4">Attempt</th>
                            <th class="p-4">Score</th>
                            <th class="p-4">Percentage</th>
                            <th class="p-4">Grade</th>
                            <th class="p-4">Time Taken</th>
                            <th class="p-4">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="history-rows" class="text-sm font-semibold text-slate-700 divide-y divide-slate-100">
                        <!-- Loaded dynamically -->
                    </tbody>
                </table>
            </div>

            <div class="mt-8 flex justify-between items-center pt-2">
                <button onclick="closeHistoryModal()" class="px-6 py-3 bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-700 font-extrabold rounded-xl transition-all text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Close Log
                </button>
                
                <button onclick="initiateDoAgainFromLog()" class="px-8 py-3 bg-bank-900 hover:bg-slate-800 text-white font-extrabold rounded-xl shadow-md transition-all text-sm flex items-center gap-2">
                    Initialize New Assessment (Do Again)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Professional Corporate Footer -->
    <footer class="bg-bank-950 text-slate-400 py-8 text-center mt-auto border-t border-slate-800">
        <div class="max-w-4xl mx-auto px-4 flex flex-col items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <p class="text-sm font-bold tracking-widest text-slate-300 uppercase mb-1">IT Selection Protocol System</p>
            <p class="text-xs text-slate-500">&copy; <?php echo date("Y"); ?> Bank Operations. Authorized access only.</p>
            <p class="text-[10px] text-slate-600 mt-2 font-mono">Designed and Architected by pume</p>
        </div>
    </footer>

    <!-- Custom Modal for Alerts/Confirms -->
    <div id="custom-modal" class="hidden fixed inset-0 z-[100] bg-bank-950/70 backdrop-blur-sm flex items-center justify-center px-4 fade-in">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center transform transition-all border border-slate-100">
            <div id="modal-icon-container" class="mx-auto w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-bank-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 id="modal-title" class="text-2xl font-extrabold text-slate-800 tracking-tight mb-2">Notice</h3>
            <p id="modal-desc" class="text-slate-500 mb-8 font-semibold"></p>
            <div class="flex flex-col sm:flex-row justify-center gap-3" id="modal-actions">
                <!-- Buttons injected by JS -->
            </div>
        </div>
    </div>

    <!-- Frontend Application Logic (Tabula Tiff Engine implementation) -->
    <script>
        // --- Core System Parameters named for pume / tabula_tiff ---
        let pumeCandidateName = '';
        let pumeExamQuestions = [];
        let pumeUserAnswers = {}; 
        let pumeHistoricalRecords = [];
        
        const PUME_TOTAL_SECONDS = 25 * 60; // 25 Minutes standard
        let tabulaTiffSecondsRemaining = PUME_TOTAL_SECONDS;
        let tabulaTiffTimerInterval = null;
        let currentPumeView = 'view-login';

        // --- TABULA TIFF STATE ENGINE (Browser Persistence) ---
        const TabulaTiffState = {
            save: function(extraParams = {}) {
                if(!pumeCandidateName) return; // don't save empty states
                const state = {
                    candidateName: pumeCandidateName,
                    currentView: currentPumeView,
                    questions: pumeExamQuestions,
                    answers: pumeUserAnswers,
                    time: tabulaTiffSecondsRemaining,
                    results: extraParams.results || null
                };
                sessionStorage.setItem('tabula_tiff_secure_state', JSON.stringify(state));
            },
            load: function() {
                const stateStr = sessionStorage.getItem('tabula_tiff_secure_state');
                if(!stateStr) return false;
                try {
                    return JSON.parse(stateStr);
                } catch(e) {
                    return false;
                }
            },
            destroy: function() {
                sessionStorage.removeItem('tabula_tiff_secure_state');
            }
        };

        // Automatic State Restoration on DOM Load
        document.addEventListener('DOMContentLoaded', () => {
            const savedState = TabulaTiffState.load();
            if(savedState && savedState.candidateName) {
                // Restore Variables
                pumeCandidateName = savedState.candidateName;
                pumeExamQuestions = savedState.questions || [];
                pumeUserAnswers = savedState.answers || {};
                tabulaTiffSecondsRemaining = savedState.time || PUME_TOTAL_SECONDS;
                
                // Restore Authenticaton UI visually
                document.getElementById('candidate_name').value = pumeCandidateName;
                document.getElementById('display_name').innerText = pumeCandidateName;
                
                const headerStatus = document.getElementById('header-status');
                headerStatus.innerText = 'Authenticated: ' + pumeCandidateName.split(' ')[0];
                headerStatus.className = 'text-xs md:text-sm font-bold bg-emerald-500/20 px-4 py-2 rounded-full border border-emerald-500/10 text-emerald-300 transition-all';
                document.getElementById('btn-signout').classList.remove('hidden');

                // Restore View Context
                if (savedState.currentView === 'view-exam') {
                    if(pumeExamQuestions.length > 0) {
                        renderQuestions();
                        startTimer(); // Picks up from saved tabulaTiffSecondsRemaining
                        showView('view-exam', false);
                    } else {
                        showView('view-ready', false);
                    }
                } else if (savedState.currentView === 'view-result' && savedState.results) {
                    displayResults(savedState.results, true); // true = isRestore prevents duplicate DB writes
                } else {
                    checkInputHistory();
                    showView('view-ready', false);
                }
            }
        });

        // --- View Navigation & Core Utility ---
        function showView(viewId, triggerSave = true) {
            ['view-login', 'view-ready', 'view-exam', 'view-result'].forEach(id => {
                document.getElementById(id).classList.add('hidden');
            });
            document.getElementById(viewId).classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            currentPumeView = viewId;
            if(triggerSave) TabulaTiffState.save();
        }

        function showLoading(text = 'Loading...') {
            document.getElementById('loading-text').innerText = text;
            document.getElementById('loading-overlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loading-overlay').classList.add('hidden');
        }

        // --- Sign Out Protocol (pume) ---
        function pumeSignOut() {
            let msg = 'Are you sure you want to securely close your active session?';
            if (currentPumeView === 'view-exam') {
                msg = 'WARNING: You are inside an active examination. Signing out will DISCARD all unsaved progress and terminate your session. Proceed?';
            }
            
            showModal('Terminate Session', msg, [
                { text: 'Cancel', style: 'default' },
                { text: 'Confirm Sign Out', style: 'danger', action: () => {
                    if (tabulaTiffTimerInterval) clearInterval(tabulaTiffTimerInterval);
                    TabulaTiffState.destroy();
                    location.reload(); // Hard reset application state
                }}
            ], currentPumeView === 'view-exam' ? 'warning' : 'info');
        }

        // --- Custom Alert Modals ---
        function showModal(title, desc, buttons, type = 'info') {
            document.getElementById('modal-title').innerText = title;
            document.getElementById('modal-desc').innerHTML = desc;
            
            const iconContainer = document.getElementById('modal-icon-container');
            if(type === 'warning') {
                iconContainer.className = 'mx-auto w-16 h-16 rounded-full bg-amber-50 flex items-center justify-center mb-4 text-amber-500 border border-amber-200';
                iconContainer.innerHTML = '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
            } else {
                iconContainer.className = 'mx-auto w-16 h-16 rounded-full bg-blue-50 flex items-center justify-center mb-4 text-bank-blue border border-blue-200';
                iconContainer.innerHTML = '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
            }

            const actionContainer = document.getElementById('modal-actions');
            actionContainer.innerHTML = '';
            
            buttons.forEach(btn => {
                const buttonEl = document.createElement('button');
                buttonEl.innerText = btn.text;
                buttonEl.className = 'w-full sm:w-auto px-6 py-3 rounded-xl font-extrabold transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 ';
                
                if (btn.style === 'danger') buttonEl.className += 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500';
                else if (btn.style === 'primary') buttonEl.className += 'bg-bank-900 hover:bg-slate-800 text-white focus:ring-slate-800';
                else buttonEl.className += 'bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 focus:ring-slate-300';
                
                buttonEl.onclick = () => {
                    document.getElementById('custom-modal').classList.add('hidden');
                    if(btn.action) btn.action();
                };
                actionContainer.appendChild(buttonEl);
            });
            
            document.getElementById('custom-modal').classList.remove('hidden');
        }

        // --- Step 1: Input Detection & History API check (tabula_tiff verification) ---
        async function checkInputHistory() {
            const nameField = document.getElementById('candidate_name').value.trim();
            const banner = document.getElementById('history-banner');
            
            if (nameField.length < 2) {
                if(banner) banner.classList.add('hidden');
                return;
            }

            try {
                const response = await fetch('index.php?action=fetch_history', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ candidate_name: nameField })
                });
                const result = await response.json();
                
                if (result.status === 'success' && result.history.length > 0) {
                    pumeHistoricalRecords = result.history;
                    if(banner) banner.classList.remove('hidden');
                } else {
                    pumeHistoricalRecords = [];
                    if(banner) banner.classList.add('hidden');
                }
            } catch (error) {
                console.warn("Log tracking failed to contact API.");
            }
        }

        function openHistoryModal() {
            const container = document.getElementById('history-rows');
            container.innerHTML = '';

            if (pumeHistoricalRecords.length === 0) {
                container.innerHTML = '<tr><td colspan="6" class="p-6 text-center text-slate-500 italic">No previous logs found for this identity.</td></tr>';
            } else {
                pumeHistoricalRecords.forEach((record, index) => {
                    const attemptNum = pumeHistoricalRecords.length - index;
                    const date = new Date(record.created_at).toLocaleDateString('en-US', {
                        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
                    });
                    const mins = Math.floor(record.time_taken_seconds / 60);
                    const secs = record.time_taken_seconds % 60;

                    const row = document.createElement('tr');
                    row.className = 'hover:bg-slate-50 border-b border-slate-100 transition-colors';
                    row.innerHTML = `
                        <td class="p-4 font-extrabold text-slate-900">Attempt #${attemptNum}</td>
                        <td class="p-4">${record.score} / ${record.total_questions}</td>
                        <td class="p-4 font-bold text-bank-blue">${Math.round(record.percentage)}%</td>
                        <td class="p-4">
                            <span class="px-2.5 py-1 rounded-md text-xs font-black ${getGradeColorClass(record.grade)}">${record.grade}</span>
                        </td>
                        <td class="p-4 text-slate-500">${mins}m ${secs}s</td>
                        <td class="p-4 text-xs text-slate-400 font-medium">${date}</td>
                    `;
                    container.appendChild(row);
                });
            }

            document.getElementById('history-modal').classList.remove('hidden');
        }

        function getGradeColorClass(grade) {
            switch(grade) {
                case 'A': return 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                case 'B': return 'bg-sky-50 text-sky-700 border border-sky-100';
                case 'C': return 'bg-amber-50 text-amber-700 border border-amber-100';
                case 'D': return 'bg-orange-50 text-orange-700 border border-orange-100';
                default: return 'bg-red-50 text-red-700 border border-red-100';
            }
        }

        function closeHistoryModal() {
            document.getElementById('history-modal').classList.add('hidden');
        }

        function initiateDoAgainFromLog() {
            closeHistoryModal();
            executeExamSuiteReset(); // Wipes current exam variables, clears UI, routes to Readiness dashboard
        }

        // --- Step 2: Login authentication ---
        document.getElementById('form-login').addEventListener('submit', function(e) {
            e.preventDefault();
            const nameInput = document.getElementById('candidate_name').value.trim();
            if(nameInput.length < 2) {
                showModal('Invalid Authentication', 'A professional name is required for profile audits.', [{text: 'OK'}], 'warning');
                return;
            }
            pumeCandidateName = nameInput;
            document.getElementById('display_name').innerText = pumeCandidateName;
            
            const headerStatus = document.getElementById('header-status');
            headerStatus.innerText = 'Authenticated: ' + pumeCandidateName.split(' ')[0];
            headerStatus.className = 'text-xs md:text-sm font-bold bg-emerald-500/20 px-4 py-2 rounded-full border border-emerald-500/10 text-emerald-300 transition-all';
            document.getElementById('btn-signout').classList.remove('hidden');
            
            showView('view-ready');
        });

        // (We no longer use goBackToLogin manually via a button as we have the explicit Sign Out button for security)

        function toggleStartBtn() {
            const checked = document.getElementById('readiness-check').checked;
            const btn = document.getElementById('start-exam-btn');
            if (checked) {
                btn.removeAttribute('disabled');
                btn.className = "w-full sm:w-auto px-10 py-4 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold shadow-lg shadow-green-200 transition-all flex items-center justify-center gap-2 cursor-pointer";
            } else {
                btn.setAttribute('disabled', 'true');
                btn.className = "w-full sm:w-auto px-10 py-4 rounded-xl bg-slate-300 text-slate-500 cursor-not-allowed font-bold transition-all flex items-center justify-center gap-2 shadow-sm";
            }
        }

        // --- Step 3: Fetch questions & establish countdown ---
        async function fetchQuestionsAndStart() {
            showLoading('Establishing Secure Operational Port...');
            try {
                const response = await fetch('index.php?action=fetch_questions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({})
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    pumeExamQuestions = result.data;
                    renderQuestions();
                    startTimer();
                    showView('view-exam');
                } else {
                    showModal('Matrix Setup Failed', result.message || 'No available exam units found in the catalog.', [{text: 'OK'}]);
                }
            } catch (error) {
                showModal('Network Interruption', 'Establishment failed. Check your localhost server connection.', [{text: 'OK'}], 'warning');
            } finally {
                hideLoading();
            }
        }

        // --- Step 4: Render Questions ---
        function renderQuestions() {
            const container = document.getElementById('questions-container');
            const navGrid = document.getElementById('question-nav-grid');
            container.innerHTML = '';
            navGrid.innerHTML = '';
            
            pumeExamQuestions.forEach((q, index) => {
                const qNum = index + 1;
                
                const card = document.createElement('div');
                card.className = 'q-card bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow';
                card.id = `q-card-${q.id}`;
                
                const header = `
                    <div class="bg-slate-50/80 border-b border-slate-100 px-6 py-4 flex justify-between items-center">
                        <span class="bg-bank-900 text-white text-xs font-black px-3.5 py-1.5 rounded-xl shadow-inner">Operational Unit ${qNum}</span>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-2.5 py-1.5 bg-white rounded-lg border border-slate-100">${q.category}</span>
                    </div>
                    <div class="p-6 md:p-8">
                        <h4 class="text-md md:text-lg font-semibold text-slate-800 mb-6 leading-relaxed">${q.question}</h4>
                `;

                const optionsList = [q.option_a, q.option_b, q.option_c, q.option_d];
                let optionsHtml = '<div class="space-y-4">';
                
                optionsList.forEach((optText, optIndex) => {
                    const inputId = `q_${q.id}_opt_${optIndex}`;
                    // Checked persistence check
                    const isChecked = (pumeUserAnswers[q.id] == optIndex) ? 'checked' : ''; 
                    
                    optionsHtml += `
                        <label for="${inputId}" class="relative block cursor-pointer group">
                            <input type="radio" name="answer_${q.id}" id="${inputId}" value="${optIndex}" class="peer sr-only" onchange="recordAnswer(${q.id}, ${qNum})" ${isChecked}>
                            <div class="flex items-center p-4 border border-slate-200 rounded-xl bg-white hover:bg-slate-50/50 hover:border-slate-300 transition-all">
                                <span class="radio-dot w-5 h-5 rounded-full border-2 border-slate-300 flex-shrink-0 mr-4 transition-colors"></span>
                                <span class="radio-text text-sm text-slate-700 transition-colors">${optText}</span>
                            </div>
                        </label>
                    `;
                });
                optionsHtml += '</div></div>';

                card.innerHTML = header + optionsHtml;
                container.appendChild(card);

                // Build Small Navigator Button
                const navBtn = document.createElement('button');
                navBtn.id = `nav-btn-${qNum}`;
                navBtn.className = 'w-full aspect-square rounded-xl border border-slate-200 text-xs font-extrabold text-slate-400 bg-slate-50 hover:bg-slate-100 transition-colors flex items-center justify-center';
                navBtn.innerText = qNum;
                navBtn.onclick = () => {
                    document.getElementById(`q-card-${q.id}`).scrollIntoView({ behavior: 'smooth', block: 'start' });
                };
                
                // Color mapping for restored answers
                if (pumeUserAnswers[q.id] !== undefined) {
                    navBtn.classList.replace('bg-slate-50', 'bg-bank-blue');
                    navBtn.classList.replace('text-slate-400', 'text-white');
                    navBtn.classList.replace('border-slate-200', 'border-bank-blue');
                }
                
                navGrid.appendChild(navBtn);
            });
            
            updateProgress(); // Sync progress bars on render
        }

        function recordAnswer(questionId, qNum) {
            pumeUserAnswers[questionId] = document.querySelector(`input[name="answer_${questionId}"]:checked`).value;
            
            updateProgress();
            TabulaTiffState.save(); // Save choice immediately to browser state
            
            const navBtn = document.getElementById(`nav-btn-${qNum}`);
            if(navBtn) {
                navBtn.classList.replace('bg-slate-50', 'bg-bank-blue');
                navBtn.classList.replace('text-slate-400', 'text-white');
                navBtn.classList.replace('border-slate-200', 'border-bank-blue');
            }
        }

        function updateProgress() {
            const answeredCount = Object.keys(pumeUserAnswers).length;
            const total = pumeExamQuestions.length;
            const percentage = total > 0 ? Math.round((answeredCount / total) * 100) : 0;
            
            document.getElementById('answered-count').innerText = answeredCount;
            document.getElementById('progress-text').innerText = `${percentage}% Complete`;
            document.getElementById('progress-bar-fill').style.width = `${percentage}%`;
        }

        // --- Step 5: Countdown Mechanisms ---
        function startTimer() {
            updateTimerDisplay();
            updateTimeBar();
            
            if(tabulaTiffTimerInterval) clearInterval(tabulaTiffTimerInterval);

            tabulaTiffTimerInterval = setInterval(() => {
                tabulaTiffSecondsRemaining--;
                updateTimerDisplay();
                updateTimeBar();
                TabulaTiffState.save(); // Checkpoint timer dynamically

                if (tabulaTiffSecondsRemaining <= 300 && tabulaTiffSecondsRemaining > 0) {
                    const tCard = document.getElementById('timer-card');
                    const tDisplay = document.getElementById('time-display');
                    const tFill = document.getElementById('time-bar-fill');
                    
                    if(!tCard.classList.contains('border-red-300')) {
                        tCard.classList.add('border-red-300', 'bg-red-50');
                        tDisplay.classList.replace('text-slate-800', 'text-red-700');
                        tFill.classList.replace('bg-bank-blue', 'bg-red-600');
                    }
                }

                if (tabulaTiffSecondsRemaining <= 0) {
                    clearInterval(tabulaTiffTimerInterval);
                    showModal('Countdown Terminated', 'Security protocol has reached the time limit. Your responses are being finalized.', [
                        { text: 'Auto-Submit', style: 'primary', action: triggerSecureGradingUI }
                    ], 'warning');
                    
                    setTimeout(triggerSecureGradingUI, 4000); 
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            if(tabulaTiffSecondsRemaining < 0) return;
            const m = Math.floor(tabulaTiffSecondsRemaining / 60);
            const s = tabulaTiffSecondsRemaining % 60;
            document.getElementById('time-display').innerText = 
                (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }

        function updateTimeBar() {
            const percentage = (tabulaTiffSecondsRemaining / PUME_TOTAL_SECONDS) * 100;
            document.getElementById('time-bar-fill').style.width = `${percentage}%`;
        }

        // --- Step 6: Beautiful Assessment Evaluation UI Loader ---
        function confirmSubmit() {
            const answeredCount = Object.keys(pumeUserAnswers).length;
            const total = pumeExamQuestions.length;
            
            let desc = `<div class="text-4xl font-black text-slate-800 mb-1">${answeredCount} / ${total}</div>
                        <div class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-6">Units Cleared</div>`;
            
            if (answeredCount < total) {
                desc += `<p class="text-sm text-amber-700 font-semibold bg-amber-50 p-4 rounded-xl border border-amber-200">${total - answeredCount} unanswered units remain. These will automatically receive 0 marks.</p>`;
            } else {
                desc += `<p class="text-sm text-emerald-700 font-semibold bg-emerald-50 p-4 rounded-xl border border-emerald-200">Excellent! All operational parameters are fully completed.</p>`;
            }

            showModal('Conclude Assessment', desc, [
                { text: 'Review Options', style: 'default' },
                { text: 'Conclude & Evaluate', style: 'primary', action: triggerSecureGradingUI }
            ]);
        }

        function triggerSecureGradingUI() {
            if (tabulaTiffTimerInterval) clearInterval(tabulaTiffTimerInterval);
            
            document.getElementById('evaluating-overlay').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });

            const stages = [
                { id: 0, text: "Decrypting response payload securely...", subtitle: "PAYLOAD RECONSTRUCT" },
                { id: 1, text: "Analyzing answers against DB matrix...", subtitle: "ANALYSIS ENGINE" },
                { id: 2, text: "Calculating weighted precision metrics...", subtitle: "METRIC COMPUTATION" },
                { id: 3, text: "Writing secure operational records...", subtitle: "SYSTEM PERSISTENCE" }
            ];

            stages.forEach((stage, idx) => {
                setTimeout(() => {
                    const row = document.getElementById(`step-${stage.id}`);
                    const icon = document.getElementById(`step-icon-${stage.id}`);
                    const subtitle = document.getElementById('evaluating-subtitle');
                    
                    subtitle.innerText = stage.subtitle;
                    row.className = "flex items-center gap-3 text-sm font-bold text-bank-blue transition-all duration-300 transform translate-x-1";
                    icon.className = "w-5 h-5 rounded-full bg-blue-50 border border-bank-blue flex items-center justify-center text-[10px] animate-pulse";
                    icon.innerHTML = `<svg class="w-3.5 h-3.5 text-bank-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>`;

                    if (idx > 0) {
                        const prevRow = document.getElementById(`step-${stage.id - 1}`);
                        prevRow.className = "flex items-center gap-3 text-sm font-semibold text-emerald-600 transition-all duration-300";
                        const prevIcon = document.getElementById(`step-icon-${stage.id - 1}`);
                        prevIcon.className = "w-5 h-5 rounded-full bg-emerald-50 border border-emerald-500 flex items-center justify-center text-emerald-600";
                    }
                    
                    if (idx === stages.length - 1) {
                        setTimeout(() => {
                            processSubmission();
                        }, 1200);
                    }
                }, idx * 1000);
            });
        }

        async function processSubmission() {
            const timeTaken = PUME_TOTAL_SECONDS - (tabulaTiffSecondsRemaining > 0 ? tabulaTiffSecondsRemaining : 0);
            const payload = {
                candidate_name: pumeCandidateName,
                time_taken: timeTaken,
                answers: pumeUserAnswers
            };

            try {
                const response = await fetch('index.php?action=submit_exam', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    displayResults(result.data, false); // Normal render, trigger save
                } else {
                    document.getElementById('evaluating-overlay').classList.add('hidden');
                    showModal('Log Write Failure', result.message || 'System error occurred while logging evaluation.', [{text: 'OK'}], 'warning');
                }
            } catch (error) {
                document.getElementById('evaluating-overlay').classList.add('hidden');
                showModal('Loss of Connection', 'We lost connection to localhost. Ensure DB services are still operational.', [{text: 'OK'}], 'warning');
            } finally {
                document.getElementById('evaluating-overlay').classList.add('hidden');
            }
        }

        // --- Step 7: Results Presentation ---
        function displayResults(data, isRestore = false) {
            document.getElementById('header-status').innerText = 'Assessment Complete';
            document.getElementById('header-status').className = 'text-xs md:text-sm font-bold bg-bank-blue/20 px-4 py-2 rounded-full border border-bank-blue/10 text-sky-400 transition-all';
            
            document.getElementById('result-candidate-name').innerText = 'Official Profile: ' + pumeCandidateName;
            document.getElementById('res-score').innerText = data.score;
            document.getElementById('res-perc').innerText = Math.round(data.percentage);
            document.getElementById('res-grade').innerText = data.grade;
            
            const mins = Math.floor(data.time_taken / 60);
            const secs = data.time_taken % 60;
            document.getElementById('res-time').innerText = `${mins}m ${secs}s`;

            const iconContainer = document.getElementById('result-icon');
            const msgContainer = document.getElementById('result-message');
            
            if (data.percentage >= 60) {
                iconContainer.className = 'w-24 h-24 mx-auto bg-emerald-50 border-4 border-emerald-100 shadow-xl rounded-full flex items-center justify-center mb-6 text-emerald-600 animate-bounce';
                iconContainer.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
                msgContainer.className = 'text-md font-bold text-emerald-800 bg-emerald-50/50 py-5 px-6 rounded-2xl border border-emerald-200 shadow-inner mt-8';
                msgContainer.innerText = 'Pass Endorsement Confirmed! You have successfully established the base operational criteria for the banking environment.';
            } else {
                iconContainer.className = 'w-24 h-24 mx-auto bg-red-50 border-4 border-red-100 shadow-xl rounded-full flex items-center justify-center mb-6 text-red-500 animate-pulse';
                iconContainer.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>`;
                msgContainer.className = 'text-md font-bold text-red-800 bg-red-50/50 py-5 px-6 rounded-2xl border border-red-200 shadow-inner mt-8';
                msgContainer.innerText = 'Minimum criteria not achieved. We suggest reviewing basic core network structures, terminal setup manuals, and backup routines.';
            }

            checkInputHistory(); // Background check for logs update
            
            showView('view-result', false);
            
            // On fresh result, inject extra parameter into state so refresh remembers grade data
            if (!isRestore) {
                TabulaTiffState.save({ results: data });
            }
        }

        async function loadCandidateHistoryOnSuccess() {
            showLoading('Sourcing past logs...');
            await checkInputHistory();
            hideLoading();
            if(pumeHistoricalRecords.length > 0) {
                openHistoryModal();
            } else {
                showModal('No Logs', 'No previous log records found under your current profile name.', [{text: 'OK'}]);
            }
        }

        // --- Try Again: Reset session safely directly to Readiness Dashboard ---
        function restartExamSuite() {
            showModal('Re-initialize Assessment', 'Initializing a new session will securely archive your current results and generate a brand new set of randomized operational questions. Do you wish to proceed?', [
                { text: 'Cancel', style: 'default' },
                { text: 'Confirm Initialization', style: 'primary', action: executeExamSuiteReset }
            ]);
        }

        function executeExamSuiteReset() {
            // Memory flush
            pumeExamQuestions = [];
            pumeUserAnswers = {};
            tabulaTiffSecondsRemaining = PUME_TOTAL_SECONDS;
            if(tabulaTiffTimerInterval) clearInterval(tabulaTiffTimerInterval);
            
            // Clean interface variables
            document.getElementById('readiness-check').checked = false;
            toggleStartBtn();
            
            // Revert strict timer styles
            const tCard = document.getElementById('timer-card');
            const tDisplay = document.getElementById('time-display');
            const tFill = document.getElementById('time-bar-fill');
            
            tCard.className = 'bg-white rounded-2xl shadow-lg border border-slate-200 p-6 text-center transform transition-all duration-300';
            tDisplay.className = 'text-4xl font-mono font-black text-slate-800 tracking-tight';
            tFill.className = 'bg-bank-blue h-full rounded-full transition-all linear';
            tFill.style.width = '100%';

            // Reroute back to Readiness screen
            showView('view-ready');
        }
    </script>
</body>
</html>