<?php

class SmtpMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $socket;
    private $log = [];
    private $isHtml = false;

    public function __construct($host, $port, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function setHtml($html = true) {
        $this->isHtml = $html;
    }

    public function send($from, $fromName, $to, $subject, $body, $replyTo = null) {
        try {
            $this->socket = @fsockopen('tls://' . $this->host, $this->port, $errno, $errstr, 30);
            
            if (!$this->socket) {
                return ['success' => false, 'error' => "Nem sikerült csatlakozni: $errstr ($errno)"];
            }

            stream_set_timeout($this->socket, 30);
            $this->getResponse();

            $this->sendCommand("EHLO localhost");
            $this->sendCommand("AUTH LOGIN");
            $this->sendCommand(base64_encode($this->username));
            $this->sendCommand(base64_encode($this->password));
            $this->sendCommand("MAIL FROM: <{$this->username}>");
            $this->sendCommand("RCPT TO: <{$to}>");
            $this->sendCommand("DATA");

            $contentType = $this->isHtml ? "text/html" : "text/plain";

            $headers  = "From: {$fromName} <{$this->username}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            if ($replyTo) {
                $headers .= "Reply-To: <{$replyTo}>\r\n";
            }
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: {$contentType}; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "\r\n";
            $headers .= chunk_split(base64_encode($body)) . "\r\n";
            $headers .= ".";

            $this->sendCommand($headers);
            $this->sendCommand("QUIT");

            fclose($this->socket);
            return ['success' => true];

        } catch (Exception $e) {
            if ($this->socket) {
                fclose($this->socket);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendCommand($command) {
        fwrite($this->socket, $command . "\r\n");
        $response = $this->getResponse();
        $this->log[] = "CMD: " . substr($command, 0, 50) . " => " . trim($response);
        
        $code = intval(substr($response, 0, 3));
        if ($code >= 400) {
            throw new Exception("SMTP hiba: " . trim($response));
        }
        
        return $response;
    }

    private function getResponse() {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    public function getLog() {
        return $this->log;
    }
}
