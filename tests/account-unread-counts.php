<?php
// Run with: php tests/account-unread-counts.php
// In-memory IMAP doubles: no live mail server, credentials, or message writes.
namespace MailSo\Imap {
    class ImapClient {
        public int $unseen = 0;
        public function SetLogger($logger): void {}
        public function FolderStatus(string $folder): object {
            if ('INBOX' !== $folder) throw new \RuntimeException('Expected INBOX status');
            return (object) ['UNSEEN' => $this->unseen];
        }
    }
}
namespace SnappyMail {
    class IDN { public static function emailToAscii(string $email): string { return $email; } }
}
namespace RainLoop\Model {
    class MainAccount { public function Email(): string { return 'main@example.test'; } }
}
namespace {
    require __DIR__ . '/../app/snappymail/v/2.38.2/app/libraries/RainLoop/Actions/Accounts.php';
    class UnreadHarness {
        use \RainLoop\Actions\Accounts;
        public string $email = '';
        public int $unseen = 16;
        public string $connected = '';
        public function GetActionParam($name, $default) { return $this->email; }
        public function getMainAccountFromToken() { return new \RainLoop\Model\MainAccount; }
        public function Logger() { return null; }
        public function DefaultResponse(array $result): array { return $result; }
        public function imapConnect($account, $select, $client): void {
            $this->connected = 'main';
            $client->unseen = $this->unseen;
        }
        protected function loadAdditionalAccountImapClient(string $email): \MailSo\Imap\ImapClient {
            if ('other@example.test' !== $email) throw new \RuntimeException('Unregistered account');
            $this->connected = 'additional';
            $client = new \MailSo\Imap\ImapClient;
            $client->unseen = $this->unseen;
            return $client;
        }
    }
    $test = new UnreadHarness;
    foreach (['main@example.test' => 'main', 'other@example.test' => 'additional'] as $email => $expected) {
        $test->email = ' ' . $email . ' ';
        $result = $test->DoAccountUnread();
        if ($result['unreadEmails'] !== 16 || $test->connected !== $expected) throw new \RuntimeException('Incorrect account status');
    }
    $test->unseen = -1;
    if ($test->DoAccountUnread()['unreadEmails'] !== 0) throw new \RuntimeException('Negative count');
    echo "Main and additional account status routing and nonnegative counts: passed.\n";
}
