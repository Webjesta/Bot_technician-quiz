# ***Bank IT Technician Selection Exam SYS.***



*Designed \& Engineered by pume (Tabula Tiff Engine V2)*



A highly secure, professional, and fully responsive single-file PHP testing suite designed to evaluate the operational excellence of aspiring IT Technicians in a banking environment.



✨ Key Features

Single-File Architecture: The entire application (Frontend UI + Backend API) is elegantly compiled into a single index.php file.

Secure API: Evaluates answers server-side to prevent browser inspection and cheating.

Tabula Tiff State Engine: Advanced browser state persistence. If a candidate accidentally refreshes or closes the page, their progress and timer are securely restored.

Dynamic Progression Tracking: Logs previous attempts based on the candidate's legal name, allowing administrators and candidates to view their performance matrix history.

Auto-Submission & Strict Timer: Enforces a strict 25-minute limit and automatically submits the payload when time expires.



***Requirements***

XAMPP / WAMP / MAMP

PHP 7.4+

MySQL / MariaDB

Setup

Start Apache + MySQL

Open http://localhost/phpmyadmin

Create database: bot

Import bot.sql

Put files in:

htdocs/bot/ (XAMPP)

Run:

http://localhost/bot/



***Database Config (if needed)***

$pume\_host = 'localhost';

$pume\_user = 'root';

$pume\_pass = '';

$pume\_name = 'bot';



***Fixes***

DB error → start MySQL + check name bot

No questions → re-import bot.sql

Styling issue → check internet (Tailwind CDN)



[**<i>~~Done.~~</i>**](Dev.pume)

