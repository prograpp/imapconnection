<?php
  //define the temp folder
  define("TMP_FOLDER", __DIR__ . "/tmp/");

  //define the data consistence mode
  //delete: data will be deleted when @Message object gets destructed
  //save: data won´t be deleted
  define("CONSISTENCE", "save");

  class IMAPConnection {
    /*
    Provide a OOD-connection to an POP3-mail account.
    -Easy access to mails
    -get receiver´s mail (important for redirect), subject, message, headers and attachments
    */

    private $connection;
    private $messages = array();

    function __construct($host, $user, $pass, $folder = "INBOX", $flags = "993/imap/ssl") {
      //get an IMAP connection from @imap_open to the @INBOX folder
      $this->connection = imap_open("{" . $host . ":" . $flags . "}$folder", $user, $pass);

      //get number of messages in the @inbox
      $totalMessages = imap_num_msg($this->connection);

      //walk through all mails
      for ($i = 1; $i <= $totalMessages; $i++) {
        $message = new Message($this->connection, $i);
        $this->messages[] = $message;
      }
    }

    function __destruct() {
      //delete marked mails
      imap_expunge($this->connection);

      //close the connection by the @close function
      imap_close($this->connection);
    }

    function getAll() {
      return $this->messages;
    }

    function length() {
      return count($this->messages);
    }

    function get($i) {
      return $this->messages[$i];
    }
  }

  class Message {
    /*
    Helper class to save e-mails.
    Contains sender, receiver, subject, message, headers and attachments
    */

    private $connection;
    private $messageID;

    private $from;
    private $fromName;
    private $to;
    private $toMailbox;
    private $subject;
    private $message;
    private $attachments = array();

    function __construct($connection, $id) {
      $this->connection = $connection;
      $this->messageID = $id;

      //get information from @header
      $header = imap_header($this->connection, $this->messageID);
      $this->from = imap_utf8("{$header->from[0]->mailbox}@{$header->from[0]->host}");
      //
      // $this->fromName = imap_utf8($header->from[0]->personal);
      $this->to = imap_utf8("{$header->to[0]->mailbox}@{$header->to[0]->host}");
      $this->toMailbox = imap_utf8($header->to[0]->mailbox);
      $this->subject = imap_utf8($header->subject);

      //get the message
      $this->message = imap_fetchbody($this->connection, $this->messageID, 1);

      //get the structure to check if there are attachments ore not
      $structure = imap_fetchstructure($this->connection, $this->messageID);
      if (isset($structure->parts)) {
        //the mail was send with attachments. save them in an temp folder and save their filenames (including path) in the @attachments array

        //create a temp folder (if not exists)
        if (!file_exists(TMP_FOLDER)) {
          mkdir(TMP_FOLDER, 0777, true);
        }

        //walk through the attachments
        foreach ($structure->parts as $key=>$value) {
          //if @value->disposition is @ATTACHMENT, it´s an attachment
          if (isset($value->disposition) && $value->disposition == "ATTACHMENT") {
            //get the filename and the content and save file in temp folder (data is in @value->dparameters)
            $params = $value->dparameters;

            //save data in vars
            $filename = imap_utf8($params[0]->value);
            $content = imap_fetchbody($this->connection, $this->messageID, $key + 1);

            //decode @content and save it in temp dir
            //if @filename already exists, rename it
            while (file_exists(TMP_FOLDER . $filename)) {
              $filename = "_$filename";
            }
            file_put_contents(TMP_FOLDER . $filename, base64_decode($content));

            //save path to file in the @attachments array
            $this->attachments[] = TMP_FOLDER . $filename;
          }
        }
      }
    }

    function __destruct() {
      //check if data has to be deleted
      if (CONSISTENCE == "delete") {
        //delete attachments
        foreach ($this->attachments as $attachment) {
          unlink($attachment);
        }
      }
    }

    function delete() {
      //delete this message from the pop3 server
      imap_delete($this->connection, $this->messageID);
    }

    function getFrom() {
      return $this->from;
    }
    function getFromName() {
      return $this->fromName;
    }
    function getTo() {
      return $this->to;
    }
    function getToMailbox() {
      return $this->toMailbox;
    }
    function getSubject() {
      return $this->subject;
    }
    function getMessage() {
      return $this->message;
    }
    function getAttachments() {
      return $this->attachments;
    }
  }
?>
