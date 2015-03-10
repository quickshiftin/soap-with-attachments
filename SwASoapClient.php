<?php
/**
 * This SoapClient implements (read: pretty broken still)
 * SwA (Soap with Attachments)
 * as specified in this W3C note
 * http://www.w3.org/TR/SOAP-attachments
 *
 * @note The download side seems to be working, the upload side,
 *       not so much, and it needs to be genericized to boot.
 */
class SwASoapClient extends SoapClient
{
    private $_bSendAsMime   = false;
    private $_bHandleAsMime = false;
    private $_aAttachments  = array();

    /**
     * Seems like we always want tracing on really, hopefully not too expensive...
     */
    public function __construct($sWsdl=null, array $aOptions=array())
    {
        $aOptions['trace'] = 1;

        parent::__construct($sWsdl, $aOptions);
    }

    public function __doRequest($request, $location, $action, $version, $one_way=0)
    {
        if($this->_bSendAsMime) {
            $request = implode('', explode(PHP_EOL, $request));

            // Create a URI to reference the attachment from the SOAP XML
            $oXml = new SimpleXMLElement($request);
            // XXX Filename field hardcoded, need a way to specify this externally
            $oNodes = $oXml->xpath('//filename');
            // XXX Hardcoded, cid: value should be set by looking at the contents of filename
            $oNodes[0]->addAttribute('href', 'cid:chicken.au');
            $request = $oXml->asXML();
            $request = trim(self::createAttachment($request));

            // Attempt at trying Content-Location.., seems an alternative to Content-ID
            // $request = str_replace('Content-ID: <chicken.au>', 'Content-Location: chicken.au', $request);

            // Extract the boundary for use in HTTP header
            preg_match('/BOUNDARY=".*"/', $request, $aMatches);
            $boundary = 'boundary=' . substr($aMatches[0], strlen('BOUNDARY='));

            $aHeaders = array(
                'Method: POST',
                'Connection: keep-alive',
                'Content-Type: Multipart/Related; type="TEXT/XML"; start="<main_envelope>"; ' . $boundary,
                'SOAPAction: ' . $action,
            );

            //die(var_dump($aHeaders));

            $ch = curl_init($location);
            curl_setopt_array(
                $ch,
                array(
                    CURLOPT_VERBOSE        => false,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $request,
                    CURLOPT_HEADER         => false,
                    CURLOPT_HTTPHEADER     => $aHeaders,
                    CURLOPT_SSL_VERIFYPEER => false,
                )
            );

            $this->_bSendAsMime = false;

            return curl_exec($ch);
        }

        // Normal operation
        $sResult = parent::__doRequest($request, $location, $action, $version, $one_way);

        // Handle and parse MIME-encoded messages
        // @note We're not doing much inspection of the XML payload against the attachments ATM
        //       so not sure how greatly this lives up to the spec
        if($this->_bHandleAsMime) {
            $sResult              = $this->_parseMimeMessage($sResult);
            $this->_bHandleAsMime = false;
        }

        return $sResult;
    }

    /**
     * Having parsed a MIME message that contains attachments,
     * this will return the list of filenames that were attached.
     */
    public function getAttachedFilenames()
    {
        return array_keys($this->_aAttachments);
    }

    /**
     * Having parsed a MIME message that contains attachments,
     * this will indicate if there were any attachments.
     */
    public function hasAttachments()
    {
        return (bool)count($this->_aAttachments);
    }

    /**
     * Fetch a particular attachment, by filename.
     */
    public function getAttachment($sFilename)
    {
        if(!isset($this->_aAttachments[$sFilename]))
            return false;

        return $this->_aAttachments[$sFilename];
    }

    /**
     * A nice shortcut method,
     * if you can expect only a single attachment in a given case.
     */
    public function getFirstAttachment(&$sFilename)
    {
        if(!$this->hasAttachments())
            return false;

        $aKeys     = $this->getAttachedFilenames();
        $sFilename = $aKeys[0];

        return $this->_aAttachments[$sFilename];
    }

    /**
     * Having parsed a MIME message that contains attachments,
     * this method let's you save one of the attachments.
     */
    public function saveAttachment($sFilename, $sDirectory)
    {
        if(!isset($this->_aAttachments[$sFilename]))
            return false;

        $this->_aAttachments[$sFilename]->save($sDirectory);

        return true;
    }

    /**
     * Given an HTTP payload containing a MIME encoded message, parse the message.
     * Return the standard SOAP (XML) payload, and store any attachments on the instance.
     */
    private function _parseMimeMessage($sLastRsp)
    {
        $oMimeParser = new PGMimeMailParser();
        $oMimeParser->setText($sLastRsp);

        // Index the attachments on this object by file name
        foreach($oMimeParser->getAttachments() as $oAttachment)
            $this->_aAttachments[$oAttachment->filename] = $oAttachment;

        // Get the standard XML payload and pass that back as a typical __doRequest would
        return $oMimeParser->getMessageBody('xml');
    }

    static public function createAttachment($sXml)
    {
        // XXX Hardcoded....
        $filename = '/tmp/chicken.au';
        $fp       = fopen($filename, 'r');
        $contents = fread($fp, filesize($filename));
        fclose($fp);

        $oFinfo = SwAFinfo::createForMime();
        $mime   = $oFinfo->mimeContentType($filename);

        $mime_parts = explode('/', $mime);

        $part1['type']     = TYPEMULTIPART;
        $part1['subtype']  = 'Related';
        //        $part1['encoding'] = ENCBINARY;

        // File attachment
        $part2['type']             = TYPEAUDIO;
        $part2['subtype']          = strtoupper($mime_parts[1]);
        $part2['encoding']         = ENCBASE64;
        $part2['id']               = '<' . basename($filename) . '>';
        // $part2['disposition.type'] = 'attachment';
        //        $part2['disposition']      = array('filename' => basename($filename));
        //        $part2['type.parameters']  = array('name' => basename($filename));
        $part2["contents.data"]    = base64_encode($contents);

        // SOAP
        $xmlPart['type']             = 'TEXT';
        $xmlPart['subtype']          = 'XML';
        $xmlPart['id']               = '<main_envelope>';
        $xmlPart['contents.data']    = $sXml;
        //        $xmlPart['disposition.type'] = 'inline';

        $body[1] = $part1;
        $body[2] = $xmlPart;
        $body[3] = $part2;

        $envelope = array();
        return imap_mail_compose($envelope, $body);
    }

    /**
     * If you're about to invoke a soap call that you expect to return a MIME-encoded
     * response, carrying with it attachments, and a standard SOAP XML payload, call
     * this method prior to making the soap call.  You'll then have access to the various
     * attachment processing methods - getAttachedFilenames, saveAttachment, hasAttachments
     */
    public function handleNextRqAsMime()
    {
        $this->_bHandleAsMime = true;
    }

    public function sendAsMime()
    {
        $this->_bSendAsMime = true;
    }
}
