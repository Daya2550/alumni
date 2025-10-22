<?php
/**
 * Simple SMTP Mailer (no external dependencies)
 * Handles basic SMTP authentication for Gmail, Outlook, etc.
 */

class SimpleSMTPMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $socket;
    private $debug = false;

    public function __construct($host, $port, $username, $password, $fromEmail, $fromName) {
        $this->host = $host;
        $this->port = (int)$port;
        $this->username = $username;
        $this->password = $password;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function setDebug($debug) {
        $this->debug = $debug;
    }

    public function send($toEmail, $subject, $htmlBody, $textBody = null) {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $this->connect();
            $this->authenticate();
            $this->sendMail($toEmail, $subject, $htmlBody, $textBody);
            $this->disconnect();
            return true;
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("SMTP Error: " . $e->getMessage());
            }
            $this->disconnect();
            return false;
        }
    }

    private function connect() {
        $this->socket = fsockopen($this->host, $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
        }
        
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '220') {
            throw new Exception("SMTP server error: $response");
        }
    }

    private function authenticate() {
        // EHLO
        $this->sendCommand("EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost');
        $response = $this->readResponse();
        
        // STARTTLS if supported
        if (strpos($response, 'STARTTLS') !== false) {
            $this->sendCommand("STARTTLS");
            $response = $this->readResponse();
            if (substr($response, 0, 3) !== '220') {
                throw new Exception("STARTTLS failed: $response");
            }
            
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS encryption failed");
            }
            
            // EHLO again after TLS
            $this->sendCommand("EHLO " . $_SERVER['HTTP_HOST'] ?? 'localhost');
            $this->readResponse();
        }

        // AUTH LOGIN
        $this->sendCommand("AUTH LOGIN");
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '334') {
            throw new Exception("AUTH LOGIN not supported: $response");
        }

        // Username
        $this->sendCommand(base64_encode($this->username));
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '334') {
            throw new Exception("Username rejected: $response");
        }

        // Password
        $this->sendCommand(base64_encode($this->password));
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '235') {
            throw new Exception("Authentication failed: $response");
        }
    }

    private function sendMail($toEmail, $subject, $htmlBody, $textBody) {
        // MAIL FROM
        $this->sendCommand("MAIL FROM:<{$this->fromEmail}>");
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("MAIL FROM failed: $response");
        }

        // RCPT TO
        $this->sendCommand("RCPT TO:<$toEmail>");
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("RCPT TO failed: $response");
        }

        // DATA
        $this->sendCommand("DATA");
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '354') {
            throw new Exception("DATA command failed: $response");
        }

        // Email headers and body
        $boundary = md5(uniqid(mt_rand(), true));
        $plainBody = $textBody ?: strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
        
        $emailData = "From: " . $this->encodeAddress($this->fromEmail, $this->fromName) . "\r\n";
        $emailData .= "To: $toEmail\r\n";
        $emailData .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $emailData .= "MIME-Version: 1.0\r\n";
        $emailData .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $emailData .= "\r\n";
        
        // Plain text part
        $emailData .= "--$boundary\r\n";
        $emailData .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $emailData .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $emailData .= $plainBody . "\r\n\r\n";
        
        // HTML part
        $emailData .= "--$boundary\r\n";
        $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
        $emailData .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $emailData .= $htmlBody . "\r\n\r\n";
        
        $emailData .= "--$boundary--\r\n";
        $emailData .= ".\r\n";

        fwrite($this->socket, $emailData);
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("Email sending failed: $response");
        }
    }

    private function sendCommand($command) {
        fwrite($this->socket, $command . "\r\n");
        if ($this->debug) {
            error_log("SMTP >> $command");
        }
    }

    private function readResponse() {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if ($this->debug) {
                error_log("SMTP << " . trim($line));
            }
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    private function disconnect() {
        if ($this->socket) {
            $this->sendCommand("QUIT");
            $this->readResponse();
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function encodeAddress($email, $name = null) {
        if (!$name) return $email;
        $encodedName = '=?UTF-8?B?' . base64_encode($name) . '?=';
        return "$encodedName <$email>";
    }
}
?>
