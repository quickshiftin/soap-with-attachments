/**
 * finfo overview -
 *
 * The functions in this module try to guess the content type and encoding of a file by
 * looking for certain magic byte sequences at specific positions within the file.
 * While this is not a bullet proof approach the heuristics used do a very good job.
 *
 * Originally there was just mime_content_type(), but that apparently was deprecated
 * in favor of the finfo extension.  The extension seems to be out-of-the-box on
 * Ubuntu installations at this point.  Mostly, this class just re-implements
 * mime_content_type(), but it can be a starting point for future finfo functionality.
 */
class SwAFinfo extends finfo
{
    /**
     * Construct an finfo derivative,
     * ensuring the MAGIC environment variable isn't set
     * so the (php) bundeled magic database will be used,
     * unless client code specifies an external magic file explicitly
     */
    public function __construct($options=FILEINFO_NONE, $magic_file=null)
    {
        if($magic_file !== null)
            putenv('MAGIC');

        parent::__construct($options, $magic_file);
    }

    /**
     * Create a PGFinfo instance for use with mime functions.
     */
    static public function createForMime($magic_file=null)
    {
        return new SwAFinfo(FILEINFO_MIME, $magic_file);
    }

    /**
     * A modern version of mime_content_type (now deprecated)
     *
     * @param string $sFile Path to the file to evaluate
     * @param string $sForcedMimeType If finfo can't infer mime type, pretend it's $sForcedMimeType
     *
     * @return string
     */
    public function mimeContentType($sFile, $sForcedMimeType='')
    {
        $sMimeType = $this->file($sFile, FILEINFO_MIME_TYPE);

        if($sMimeType == false && $sForcedMimeType !== '')
            return $sForcedMimeType;

        return $sMimeType;
    }

    /**
     * A static convenience method to replace mime_content_type,
     * because we miss the old days!
     */
    static public function mimeContentTypeS($sFile, $sForcedMimeType='', $sMagicFile=null)
    {
        return self::createForMime($sMagicFile)
            ->mimeContentType($sFile, $sForcedMimeType);
    }
}
