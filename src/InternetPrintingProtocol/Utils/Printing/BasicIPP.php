<?php

declare (strict_types = 1);

namespace InternetPrintingProtocol\Utils;

use InternetPrintingProtocol\Exception\IppException;

class BasicIPP
{
    protected $logLevel = 2;
    protected $logType = 3; // timeout at http connection (seconds) 0 => default => 30.
    protected $logDestination; // data reading timeout (milliseconds) 0 => default => 30.
    protected $serverOutput;
    protected $setup; // max 3: almost silent
        protected $stringJob; // debugging purpose: echo "END tag OK" if (1 and  reads while end tag)
        protected $data; // compatibility mode for old scripts
    protected $debugCount = 0;
    protected $username;
    protected $charset;
    protected $password;
    protected $requestingUser;
    protected $client_hostname = 'localhost';
    protected $stream; // object you can read: attributes after validateJob()
        protected $host = 'localhost'; // object you can read: printer's attributes after getPrinterAttributes()
        protected $port = '631'; // object you can read: last job attributes
        protected $requesting_user = ''; // object you can read: jobs attributes after getJobs()
    protected $printer_uri;
    protected $timeoutSeconds = '20';
    protected $errNo;
    protected $errStr;
    protected $dataType;
    protected $dataHead;
    protected $dataTail; // max 3: very verbose
        protected $operation_id; // 3: file | 1: e-mail | 0: logger
        protected $delay; // e-mail or file
    protected $errorGeneration;
    protected $debugHttp = 0;
    protected $noDisconnect;
    protected $jobTags;
    protected $operationTags;
    protected $index;
    protected $collection;
    protected $collectionIndex;
    protected $collectionKey = [];
    protected $collectionDepth = -1;
    protected $endCollection = false;
    protected $collectionNbr = [];
    protected $unix = false;
    protected $output;
    public $paths = [
        'root' => '/',
        'admin' => '/admin/',
        'printers' => '/printers/',
        'jobs' => '/jobs/',
    ];
    public $httpTimeout = 30;
    public $httpDataTimeout = 30;
    public $ssl = false;
    public $debugLevel = 3;
    public $alertOnEndTag;
    public $withExceptions = 0;
    public $handleHttpExceptions = 1;
    public $jobs = [];
    public $jobsUri = [];
    public $status = [];
    public $response_completed = [];
    public $lastJob = '';
    public $attributes;
    public $printer_attributes;
    public $jobAttributes; //RFC3382
    public $jobsAttributes; //RFC3382
        public $available_printers = []; //RFC3382
        public $printer_map = []; //RFC3382
        public $printers_uri = []; //RFC3382
        public $debug = []; //RFC3382
        public $response; // true -> use unix sockets instead of http
    public $meta;

    public function __construct()
    {
        $tz = getenv('date.timezone');
        if (!$tz) {
            $tz = @date_default_timezone_get();
        }

        date_default_timezone_set($tz);
        $this->meta = new \stdClass();
        $this->setup = new \stdClass();
        $this->values = new \stdClass();
        $this->serverOutput = new \stdClass();
        $this->errorGeneration = new \stdClass();
        $this->_parsing = new \stdClass();
        $this->_initTags();
    }

    protected function _initTags()
    {
        $this->tags_types = [
            'unsupported' => [
                'tag' => \chr(0x10),
                'build' => '',
            ],
            'reserved' => [
                'tag' => \chr(0x11),
                'build' => '',
            ],
            'unknown' => [
                'tag' => \chr(0x12),
                'build' => '',
            ],
            'no-value' => [
                'tag' => \chr(0x13),
                'build' => 'no_value',
            ],
            'integer' => [
                'tag' => \chr(0x21),
                'build' => 'integer',
            ],
            'boolean' => [
                'tag' => \chr(0x22),
                'build' => 'boolean',
            ],
            'enum' => [
                'tag' => \chr(0x23),
                'build' => 'enum',
            ],
            'octetString' => [
                'tag' => \chr(0x30),
                'build' => 'octet_string',
            ],
            'datetime' => [
                'tag' => \chr(0x31),
                'build' => 'datetime',
            ],
            'resolution' => [
                'tag' => \chr(0x32),
                'build' => 'resolution',
            ],
            'rangeOfInteger' => [
                'tag' => \chr(0x33),
                'build' => 'range_of_integers',
            ],
            'textWithLanguage' => [
                'tag' => \chr(0x35),
                'build' => 'string',
            ],
            'nameWithLanguage' => [
                'tag' => \chr(0x36),
                'build' => 'string',
            ],
            /*
                   "text" => array ("tag" => chr(0x40),
                   "build" => "string"),
                   "text string" => array ("tag" => chr(0x40),
                   "build" => "string"),
                 */
            'textWithoutLanguage' => [
                'tag' => \chr(0x41),
                'build' => 'string',
            ],
            'nameWithoutLanguage' => [
                'tag' => \chr(0x42),
                'build' => 'string',
            ],
            'keyword' => [
                'tag' => \chr(0x44),
                'build' => 'string',
            ],
            'uri' => [
                'tag' => \chr(0x45),
                'build' => 'string',
            ],
            'uriScheme' => [
                'tag' => \chr(0x46),
                'build' => 'string',
            ],
            'charset' => [
                'tag' => \chr(0x47),
                'build' => 'string',
            ],
            'naturalLanguage' => [
                'tag' => \chr(0x48),
                'build' => 'string',
            ],
            'mimeMediaType' => [
                'tag' => \chr(0x49),
                'build' => 'string',
            ],
            'extendedAttributes' => [
                'tag' => \chr(0x7F),
                'build' => 'extended',
            ],
        ];
        $this->operationTags = [
            'compression' => [
                'tag' => 'keyword',
            ],
            'document-natural-language' => [
                'tag' => 'naturalLanguage',
            ],
            'job-k-octets' => [
                'tag' => 'integer',
            ],
            'job-impressions' => [
                'tag' => 'integer',
            ],
            'job-media-sheets' => [
                'tag' => 'integer',
            ],
        ];
        $this->jobTags = [
            'job-priority' => [
                'tag' => 'integer',
            ],
            'job-hold-until' => [
                'tag' => 'keyword',
            ],
            'job-sheets' => [
                'tag' => 'keyword',
            ], //banner page
            'multiple-document-handling' => [
                'tag' => 'keyword',
            ],
            //"copies" => array("tag" => "integer"),
            'finishings' => [
                'tag' => 'enum',
            ],
            //"page-ranges" => array("tag" => "rangeOfInteger"), // has its own function
            //"sides" => array("tag" => "keyword"), // has its own function
            'number-up' => [
                'tag' => 'integer',
            ],
            'orientation-requested' => [
                'tag' => 'enum',
            ],
            'media' => [
                'tag' => 'keyword',
            ],
            'printer-resolution' => [
                'tag' => 'resolution',
            ],
            'print-quality' => [
                'tag' => 'enum',
            ],
            'job-message-from-operator' => [
                'tag' => 'textWithoutLanguage',
            ],
        ];
        $this->printer_tags = [
            'requested-attributes' => [
                'tag' => 'keyword',
            ],
        ];
    }

    protected function _setJobUri($job_uri)
    {
        $this->meta->job_uri = \chr(0x45) // type uri
            .\chr(0x00).\chr(0x07) // name-length
            .'job-uri'
            .$this->_giveMeStringLength($job_uri).$job_uri;
        $this->_putDebug('job-uri is: '.$job_uri, 2);
    }

    public function setPort($port = '631')
    {
        $this->port = $port;
        $this->_putDebug('Port is '.$this->port, 2);
    }

    protected function _putDebug($string, $level = 1)
    {
        if ($level === false) {
            return;
        }

        if ($level < $this->debugLevel) {
            return;
        }

        $this->debug[$this->debugCount] = substr($string, 0, 1024);
        ++$this->debugCount;
    }

    public function setUnix($socket = '/var/run/cups/cups.sock')
    {
        $this->host = $socket;
        $this->unix = true;
        $this->_putDebug('Host is '.$this->host, 2);
    }

    public function setHost($host = 'localhost')
    {
        $this->host = $host;
        $this->unix = false;
        $this->_putDebug('Host is '.$this->host, 2);
    }

    public function setTimeoutSeconds($timeoutSeconds)
    {
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function setData($data)
    {
        $this->data = $data;
        $this->_putDebug('Data set', 2);
    }

    public function setRawText()
    {
        $output = [];

        $this->setup->datatype = 'TEXT';
        $this->meta->mime_media_type = '';
        $this->setup->mime_media_type = 1;
        $this->dataHead = \chr(0x16);
        if (is_readable($this->data)) {
            //It's a filename.  Open and stream.
            $data = fopen($this->data, 'rb');
            while (!feof($data)) {
                $output = fread($data, 8192);
            }
        } else {
            $output = $this->data;
        }
        if ($output[\strlen($output) - 1] !== \chr(0x0c)) {
            if (!isset($this->setup->noFormFeed)) {
                $this->dataTail = \chr(0x0c);
            }
        }
        $this->_putDebug(_('Forcing data to be interpreted as RAW TEXT'), 2);
    }

    public function setFormFeed()
    {
        $this->dataTail = "\r\n".\chr(0x0c);
        unset($this->setup->noFormFeed);
    }

    public function unsetFormFeed()
    {
        $this->dataTail = '';
        $this->setup->noFormFeed = 1;
    }

    public function setDocumentName($document_name = '')
    {
        $this->meta->document_name = '';
        if (!$document_name) {
            return true;
        }
        $document_name = substr($document_name, 0, 1023);
        $length = \strlen($document_name);
        $length = \chr($length);
        while (\strlen($length) < 2) {
            $length = \chr(0x00).$length;
        }
        $this->_putDebug(sprintf(_('document name: %s'), $document_name), 2);
        $this->meta->document_name = \chr(0x41) // textWithoutLanguage tag
            .\chr(0x00).\chr(0x0d) // name-length
            .'document-name' // mimeMediaType
            .$this->_giveMeStringLength($document_name).$document_name; // value
    }

    public function setAuthentication($username, $password)
    {
        $this->password = $password;
        $this->username = $username;
        $this->_putDebug(_('Setting password'), 2);
        $this->setup->password = 1;
    }

    public function setSides($sides = 2)
    {
        $this->meta->sides = '';
        if (!$sides) {
            return true;
        }

        switch ($sides) {
            case 1:
                $sides = 'one-sided';
                break;

            case 2:
                $sides = 'two-sided-long-edge';
                break;

            case '2CE':
                $sides = 'two-sided-short-edge';
                break;
        }

        $this->meta->sides = \chr(0x44) // keyword type | value-tag
            .\chr(0x00).\chr(0x05) //        name-length
            .'sides' // sides |             name
            .$this->_giveMeStringLength($sides) //               value-length
            .$sides; // one-sided |          value
        $this->_putDebug(sprintf(_('Sides value set to %s'), $sides), 2);
    }

    // setDocumentFormat alias for backward compatibility

    public function setFidelity()
    {
        // whether the server can't replace any attributes
        // (eg, 2 sided print is not possible,
        // so print one sided) and DO NOT THE JOB.
        $this->meta->fidelity = \chr(0x22) // boolean type  |  value-tag
            .\chr(0x00).\chr(0x16) //                  name-length
            .'ipp-attribute-fidelity' // ipp-attribute-fidelity | name
            .\chr(0x00).\chr(0x01) //  value-length
            .\chr(0x01); //  true | value
        $this->_putDebug(_('Fidelity attribute is set (paranoid mode)'), 3);
    }

    public function unsetFidelity()
    {
        // whether the server can replace any attributes
        // (eg, 2 sided print is not possible,
        // so print one sided) and DO THE JOB.
        $this->meta->fidelity = \chr(0x22) //  boolean type | value-tag
            .\chr(0x00).\chr(0x16) //        name-length
            .'ipp-attribute-fidelity' // ipp-attribute-fidelity | name
            .\chr(0x00).\chr(0x01) //               value-length
            .\chr(0x00); // false |                   value
        $this->_putDebug(_('Fidelity attribute is unset'), 2);
    }

    public function setMessage($message = '')
    {
        $this->meta->message = '';
        if (!$message) {
            return true;
        }
        $this->meta->message =
            \chr(0x41) // attribute type = textWithoutLanguage
            .\chr(0x00)
            .\chr(0x07)
            .'message'
            .$this->_giveMeStringLength(substr($message, 0, 127))
            .substr($message, 0, 127);
        $this->_putDebug(sprintf(_('Setting message to "%s"'), $message), 2);
    }

    public function setPageRanges($page_ranges)
    {
        // $pages_ranges = string:  "1:5 10:25 40:52 ..."
        // to unset, specify an empty string.
        $this->meta->page_range = '';
        if (!$page_ranges) {
            return true;
        }
        $page_ranges = trim(str_replace('-', ':', $page_ranges));
        $first = true;
        #$page_ranges = split(' ', $page_ranges);
        $page_ranges = explode(' ', $page_ranges);
        foreach ($page_ranges as $page_range) {
            $value = $this->_rangeOfIntegerBuild($page_range);
            if ($first) {
                $this->meta->page_ranges .=
                    $this->tags_types['rangeOfInteger']['tag']
                    .$this->_giveMeStringLength('page-ranges')
                    .'page-ranges'
                    .$this->_giveMeStringLength($value)
                    .$value;
            } else {
                $this->meta->page_ranges .=
                    $this->tags_types['rangeOfInteger']['tag']
                    .$this->_giveMeStringLength('')
                    .$this->_giveMeStringLength($value)
                    .$value;
                $first = false;
            }
        }
    }

    protected function _rangeOfIntegerBuild($integers): string
    {
        $outValue = [];

        $integers = explode(':', $integers);

        for ($i = 0; $i < 2; ++$i) {
            $outValue[$i] = $this->_integerBuild($integers[$i]);
        }

        return $outValue[0].$outValue[1];
    }

    public function setAttribute($attribute, $values)
    {
        $operation_attributes_tags = array_keys($this->operationTags);
        $job_attributes_tags = array_keys($this->jobTags);
        $printer_attributes_tags = array_keys($this->printer_tags);
        $this->unsetAttribute($attribute);
        if (\in_array($attribute, $operation_attributes_tags, true)) {
            if (\is_array($values)) {
                foreach ($values as $value) {
                    $this->_setOperationAttribute($attribute, $value);
                }
            } else {
                $this->_setOperationAttribute($attribute, $values);
            }
        } elseif (\in_array($attribute, $job_attributes_tags, true)) {
            if (\is_array($values)) {
                foreach ($values as $value) {
                    $this->_setJobAttribute($attribute, $value);
                }
            } else {
                $this->_setJobAttribute($attribute, $values);
            }
        } elseif (\in_array($attribute, $printer_attributes_tags, true)) {
            if (\is_array($values)) {
                foreach ($values as $value) {
                    $this->_setPrinterAttribute($attribute, $value);
                }
            } else {
                $this->_setPrinterAttribute($attribute, $values);
            }
        } else {
            trigger_error(sprintf(_(
                'SetAttribute: Tag "%s" is not a printer or a job attribute'),
                $attribute
            ));

            $this->_putDebug(sprintf(_(
                'SetAttribute: Tag "%s" is not a printer or a job attribute'),
                $attribute
            ), 3);

            $this->_errorLog(sprintf(_('SetAttribute: Tag "%s" is not a printer or a job attribute'),
                $attribute
            ), 2);

            return false;
        }
    }

    public function unsetAttribute($attribute): bool
    {
        $operation_attributes_tags = array_keys($this->operationTags);
        $job_attributes_tags = array_keys($this->jobTags);
        $printer_attributes_tags = array_keys($this->printer_tags);
        if (\in_array($attribute, $operation_attributes_tags, true)) {
            unset(
                $this->operationTags[$attribute]['value'],
                $this->operationTags[$attribute]['systag']
            );
        } elseif (\in_array($attribute, $job_attributes_tags, true)) {
            unset(
                $this->jobTags[$attribute]['value'],
                $this->jobTags[$attribute]['systag']
            );
        } elseif (\in_array($attribute, $printer_attributes_tags, true)) {
            unset(
                $this->printer_tags[$attribute]['value'],
                $this->printer_tags[$attribute]['systag']
            );
        } else {
            trigger_error(
                sprintf(_('unsetAttribute: Tag "%s" is not a printer or a job attribute'),
                    $attribute));
            $this->_putDebug(
                sprintf(_('unsetAttribute: Tag "%s" is not a printer or a job attribute'),
                    $attribute), 3);
            $this->_errorLog(
                sprintf(_('unsetAttribute: Tag "%s" is not a printer or a job attribute'),
                    $attribute), 2);

            return false;
        }

        return true;
    }

    protected function _errorLog($stringToLog, $level)
    {
        if ($level > $this->logLevel) {
            return;
        }

        $string = sprintf('%s : %s:%s user %s : %s', basename($_SERVER['PHP_SELF']), $this->host, $this->port,
            $this->requesting_user, $stringToLog);

        if ($this->logType === 0) {
            error_log($string);

            return;
        }

        $string = sprintf("%s %s Host %s:%s user %s : %s\n", date('M d H:i:s'), basename($_SERVER['PHP_SELF']),
            $this->host, $this->port, $this->requesting_user, $stringToLog);
        error_log($string, $this->logType, $this->logDestination);
    }

    protected function _setOperationAttribute($attribute, $value)
    {
        //used by setAttribute
        $tag_type = $this->operationTags[$attribute]['tag'];
        switch ($tag_type) {
            case 'integer':
                $this->operationTags[$attribute]['value'][] = $this->_integerBuild($value);
                break;

            case 'keyword':
            case 'naturalLanguage':
                $this->operationTags[$attribute]['value'][] = $value;
                break;

            default:
                trigger_error(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute));
                $this->_putDebug(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute), 2);
                $this->_errorLog(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute), 2);

                return false;
                break;
        }
        $this->operationTags[$attribute]['systag'] = $this->tags_types[$tag_type]['tag'];
    }

    protected function _setJobAttribute($attribute, $value)
    {
        $unit = '';
        $tag_type = $this->jobTags[$attribute]['tag'];

        switch ($tag_type) {
            case 'integer':
                $this->jobTags[$attribute]['value'][] = $this->_integerBuild($value);
                break;

            case 'nameWithoutLanguage':
            case 'nameWithLanguage':
            case 'textWithoutLanguage':
            case 'textWithLanguage':
            case 'keyword':
            case 'naturalLanguage':
                $this->jobTags[$attribute]['value'][] = $value;
                break;

            case 'enum':
                $value = $this->_enumBuild($attribute, $value); // may be overwritten by children
                $this->jobTags[$attribute]['value'][] = $value;
                break;

            case 'rangeOfInteger':
                // $value have to be: INT1:INT2 , eg 100:1000
                $this->jobTags[$attribute]['value'][] = $this->_rangeOfIntegerBuild($value);
                break;

            case 'resolution':
                if (false !== strpos($value, 'dpi')) {
                    $unit = \chr(0x3);
                }
                if (false !== strpos($value, 'dpc')) {
                    $unit = \chr(0x4);
                }
                $search = [
                    '#(dpi|dpc)#',
                    '#(x|-)#',
                ];
                $replace = [
                    '',
                    ':',
                ];
                $value = $this->_rangeOfIntegerBuild(preg_replace($search, $replace, $value)).$unit;
                $this->jobTags[$attribute]['value'][] = $value;
                break;

            default:
                trigger_error(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute));
                $this->_putDebug(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute), 2);
                $this->_errorLog(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute), 2);

                return false;
                break;
        }
        $this->jobTags[$attribute]['systag'] = $this->tags_types[$tag_type]['tag'];
    }

    protected function _enumBuild($tag, $value): string
    {
        switch ($tag) {
            case 'orientation-requested':
                switch ($value) {
                    case 'portrait':
                        $value = \chr(3);
                        break;

                    case 'landscape':
                        $value = \chr(4);
                        break;

                    case 'reverse-landscape':
                        $value = \chr(5);
                        break;

                    case 'reverse-portrait':
                        $value = \chr(6);
                        break;
                }
                break;

            case 'print-quality':
                switch ($value) {
                    case 'draft':
                        $value = \chr(3);
                        break;

                    case 'normal':
                        $value = \chr(4);
                        break;

                    case 'high':
                        $value = \chr(5);
                        break;
                }
                break;

            case 'finishing':
                switch ($value) {
                    case 'none':
                        $value = \chr(3);
                        break;

                    case 'staple':
                        $value = \chr(4);
                        break;

                    case 'punch':
                        $value = \chr(5);
                        break;

                    case 'cover':
                        $value = \chr(6);
                        break;

                    case 'bind':
                        $value = \chr(7);
                        break;

                    case 'saddle-stitch':
                        $value = \chr(8);
                        break;

                    case 'edge-stitch':
                        $value = \chr(9);
                        break;

                    case 'staple-top-left':
                        $value = \chr(20);
                        break;

                    case 'staple-bottom-left':
                        $value = \chr(21);
                        break;

                    case 'staple-top-right':
                        $value = \chr(22);
                        break;

                    case 'staple-bottom-right':
                        $value = \chr(23);
                        break;

                    case 'edge-stitch-left':
                        $value = \chr(24);
                        break;

                    case 'edge-stitch-top':
                        $value = \chr(25);
                        break;

                    case 'edge-stitch-right':
                        $value = \chr(26);
                        break;

                    case 'edge-stitch-bottom':
                        $value = \chr(27);
                        break;

                    case 'staple-dual-left':
                        $value = \chr(28);
                        break;

                    case 'staple-dual-top':
                        $value = \chr(29);
                        break;

                    case 'staple-dual-right':
                        $value = \chr(30);
                        break;

                    case 'staple-dual-bottom':
                        $value = \chr(31);
                        break;
                }
                break;
        }
        $prepend = '';
        while ((\strlen($value) + \strlen($prepend)) < 4) {
            $prepend .= \chr(0);
        }

        return $prepend.$value;
    }

    protected function _setPrinterAttribute($attribute, $value)
    {
        $tag_type = $this->printer_tags[$attribute]['tag'];
        switch ($tag_type) {
            case 'integer':
                $this->printer_tags[$attribute]['value'][] = $this->_integerBuild($value);
                break;

            case 'keyword':
            case 'naturalLanguage':
                $this->printer_tags[$attribute]['value'][] = $value;
                break;

            default:
                trigger_error(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute));
                $this->_putDebug(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute), 2);
                $this->_errorLog(sprintf(_('SetAttribute: Tag "%s": cannot set attribute'), $attribute), 2);

                return false;
                break;
        }

        $this->printer_tags[$attribute]['systag'] = $this->tags_types[$tag_type]['tag'];
    }

    /**
     * Sets log file destination. Creates the file if has permission.
     *
     * @param string $log_destination
     * @param string $destination_type
     * @param int    $level
     *
     * @throws IppException
     */
    public function setLog(string $log_destination, string $destination_type = 'file', $level = 2)
    {
        if (!file_exists($log_destination) && is_writable(\dirname($log_destination))) {
            touch($log_destination);
            chmod($log_destination, 0777);
        }

        switch ($destination_type) {
            case 'file':
            case 3:
                $this->logDestination = $log_destination;
                $this->logType = 3;
                break;

            case 'logger':
            case 0:
                $this->logDestination = '';
                $this->logType = 0;
                break;

            case 'e-mail':
            case 1:
                $this->logDestination = $log_destination;
                $this->logType = 1;
                break;
        }
        $this->logLevel = $level;
    }

    public function printDebug()
    {
        for ($i = 0; $i < $this->debugCount; ++$i) {
            echo $this->debug[$i], "\n";
        }
        $this->debug = [];
        $this->debugCount = 0;
    }

    public function getDebug(): string
    {
        $debug = '';
        for ($i = 0; $i < $this->debugCount; ++$i) {
            $debug .= $this->debug[$i];
        }
        $this->debug = [];
        $this->debugCount = 0;

        return $debug;
    }

    public function printJob()
    {
        // this BASIC version of printJob do not parse server
        // output for job's attributes
        $this->_putDebug(
            sprintf(
                '************** Date: %s ***********',
                date('Y-m-d H:i:s')
            )
        );
        if (!$this->_stringJob()) {
            return false;
        }
        if (is_readable($this->data)) {
            $this->_putDebug(_('Printing a FILE'));
            $this->output = $this->stringJob;
            if ($this->setup->datatype === 'TEXT') {
                $this->output .= \chr(0x16);
            }
            $post_values = [
                'Content-Type' => 'application/ipp',
                'Data' => $this->output,
                'File' => $this->data,
            ];
            if ($this->setup->datatype === 'TEXT' && !isset($this->setup->noFormFeed)) {
                $post_values = array_merge(
                    $post_values,
                    [
                        'Filetype' => 'TEXT',
                    ]
                );
            }
        } else {
            $this->_putDebug(_('Printing DATA'));
            $this->output =
                $this->stringJob
                .$this->dataHead
                .$this->data
                .$this->dataTail;
            $post_values = [
                'Content-Type' => 'application/ipp',
                'Data' => $this->output,
            ];
        }
        if ($this->_sendHttp($post_values, $this->paths['printers'])) {
            $this->_parseServerOutput();
        }
        if (isset($this->serverOutput, $this->serverOutput->status)) {
            $this->status = array_merge($this->status, [
                $this->serverOutput->status,
            ]);
            if ($this->serverOutput->status === 'successful-ok') {
                $this->_errorLog(
                    sprintf('printing job %s: ', $this->lastJob)
                    .$this->serverOutput->status,
                    3);
            } else {
                $this->_errorLog(
                    sprintf('printing job: %s', $this->lastJob)
                    .$this->serverOutput->status,
                    1);
            }

            return $this->serverOutput->status;
        }

        $this->status =
            array_merge($this->status, ['OPERATION FAILED']);
        $this->jobs =
            array_merge($this->jobs, ['']);
        $this->jobsUri =
            array_merge($this->jobsUri, ['']);

        $this->_errorLog('printing job : OPERATION FAILED', 1);

        return false;
    }

    //
    // OPERATIONS
    //

    protected function _stringJob(): bool
    {
        if (!isset($this->setup->charset)) {
            $this->setCharset();
        }
        if (!isset($this->setup->datatype)) {
            $this->setBinary();
        }
        if (!isset($this->setup->uri)) {
            $this->getPrinters();
            unset($this->jobs[\count($this->jobs) - 1], $this->jobsUri[\count($this->jobsUri) - 1], $this->status[\count($this->status) - 1]);
            if (array_key_exists(0, $this->available_printers)) {
                $this->setPrinterURI($this->available_printers[0]);
            } else {
                trigger_error(
                    _('_stringJob: Printer URI is not set: die'),
                    E_USER_WARNING);
                $this->_putDebug(_('_stringJob: Printer URI is not set: die'), 4);
                $this->_errorLog(' Printer URI is not set, die', 2);

                return false;
            }
        }
        if (!isset($this->setup->copies)) {
            $this->setCopies();
        }
        if (!isset($this->setup->language)) {
            $this->setLanguage();
        }
        if (!isset($this->setup->mime_media_type)) {
            $this->setMimeMediaType();
        }
        if (!isset($this->setup->jobname)) {
            $this->setJobName();
        }
        unset($this->setup->jobname);
        if (!isset($this->meta->username)) {
            $this->setUserName();
        }
        if (!isset($this->meta->fidelity)) {
            $this->meta->fidelity = '';
        }
        if (!isset($this->meta->document_name)) {
            $this->meta->document_name = '';
        }
        if (!isset($this->meta->sides)) {
            $this->meta->sides = '';
        }
        if (!isset($this->meta->page_ranges)) {
            $this->meta->page_ranges = '';
        }
        $jobAttributes = '';
        $operationAttributes = '';
        $printerAttributes = '';
        $this->_buildValues($operationAttributes, $jobAttributes, $printerAttributes);
        $this->_setOperationId();
        if (!isset($this->errorGeneration->request_body_malformed)) {
            $this->errorGeneration->request_body_malformed = '';
        }
        $this->stringJob = \chr(0x01).\chr(0x01) // 1.1  | version-number
            .\chr(0x00).\chr(0x02) // Print-Job | operation-id
            .$this->meta->operation_id //           request-id
            .\chr(0x01) // start operation-attributes | operation-attributes-tag
            .$this->meta->charset
            .$this->meta->language
            .$this->meta->printer_uri
            .$this->meta->username
            .$this->meta->jobname
            .$this->meta->fidelity
            .$this->meta->document_name
            .$this->meta->mime_media_type
            .$operationAttributes;
        if ($this->meta->copies || $this->meta->sides || $this->meta->page_ranges || !empty($jobAttributes)) {
            $this->stringJob .=
                \chr(0x02) // start job-attributes | job-attributes-tag
                .$this->meta->copies
                .$this->meta->sides
                .$this->meta->page_ranges
                .$jobAttributes;
        }
        $this->stringJob .= \chr(0x03); // end-of-attributes | end-of-attributes-tag
        $this->_putDebug(
            sprintf(_('String sent to the server is: %s'),
                $this->stringJob)
        );

        return true;
    }

    //
    // HTTP OUTPUT
    //

    public function setCharset($charset = 'utf-8')
    {
        $charset = strtolower($charset);
        $this->charset = $charset;
        $this->meta->charset = \chr(0x47) // charset type | value-tag
            .\chr(0x00).\chr(0x12) // name-length
            .'attributes-charset' // attributes-charset | name
            .$this->_giveMeStringLength($charset) // value-length
            .$charset; // value
        $this->_putDebug(sprintf(_('Charset: %s'), $charset), 2);
        $this->setup->charset = 1;
    }

    //
    // INIT
    //

    protected function _giveMeStringLength($string): string
    {
        $length = \strlen($string);
        if ($length > ((0xFF << 8) + 0xFF)) {
            $errorMessage = sprintf(
                _('max string length for an ipp meta-information = %d, while here %d'),
                (0xFF << 8) + 0xFF, $length);

            if ($this->withExceptions) {
                throw new ippException($errorMessage);
            }

            trigger_error($errorMessage, E_USER_ERROR);
        }
        $int1 = $length & 0xFF;
        $length -= $int1;
        $length >>= 8;
        $int2 = $length & 0xFF;

        return \chr($int2).\chr($int1);
    }

    //
    // SETUP
    //

    public function setPrinterURI($uri)
    {
        $length = \strlen($uri);
        $length = \chr($length);
        while (\strlen($length) < 2) {
            $length = \chr(0x00).$length;
        }
        $this->meta->printer_uri = \chr(0x45) // uri type | value-tag
            .\chr(0x00).\chr(0x0B) // name-length
            .'printer-uri' // printer-uri | name
            .$length.$uri;
        $this->printer_uri = $uri;
        $this->_putDebug(sprintf(_('Printer URI: %s'), $uri), 2);
        $this->setup->uri = 1;
    }

    public function setCopies($NbrCopies = 1)
    {
        $this->meta->copies = '';

        if ($NbrCopies === 1 || !$NbrCopies) {
            return true;
        }

        $copies = $this->_integerBuild($NbrCopies);
        $this->meta->copies = \chr(0x21) // integer type | value-tag
            .\chr(0x00).\chr(0x06) //             name-length
            .'copies' // copies    |             name
            .$this->_giveMeStringLength($copies) // value-length
            .$copies;
        $this->_putDebug(sprintf(_('Copies: %s'), $NbrCopies), 2);
        $this->setup->copies = 1;
    }

    protected function _integerBuild($value): string
    {
        if ($value >= 2147483647 || $value < -2147483648) {
            trigger_error(
                _("Values must be between -2147483648 and 2147483647: assuming '0'"), E_USER_WARNING);

            return \chr(0x00).\chr(0x00).\chr(0x00).\chr(0x00);
        }
        $initial_value = $value;
        $int1 = $value & 0xFF;
        $value -= $int1;
        $value >>= 8;
        $int2 = $value & 0xFF;
        $value -= $int2;
        $value >>= 8;
        $int3 = $value & 0xFF;
        $value -= $int3;
        $value >>= 8;
        $int4 = $value & 0xFF;
        if ($initial_value < 0) {
            $int4 = \chr($int4) | \chr(0x80);
        } else {
            $int4 = \chr($int4);
        }
        $value = $int4.\chr($int3).\chr($int2).\chr($int1);

        return $value;
    }

    //
    // RESPONSE PARSING
    //

    public function setLanguage($language = 'en_us')
    {
        $language = strtolower($language);
        $this->meta->language = \chr(0x48) // natural-language type | value-tag
            .\chr(0x00).\chr(0x1B) //  name-length
            .'attributes-natural-language' //attributes-natural-language
            .$this->_giveMeStringLength($language) // value-length
            .$language; // value
        $this->_putDebug(sprintf(_('Language: %s'), $language), 2);
        $this->setup->language = 1;
    }

    public function setMimeMediaType($mime_media_type = 'application/octet-stream')
    {
        $this->setDocumentFormat($mime_media_type);
    }

    public function setDocumentFormat($mime_media_type = 'application/octet-stream')
    {
        $this->setBinary();
        $length = \chr(\strlen($mime_media_type));
        while (\strlen($length) < 2) {
            $length = \chr(0x00).$length;
        }
        $this->_putDebug(sprintf(_('mime type: %s'), $mime_media_type), 2);
        $this->meta->mime_media_type = \chr(0x49) // document-format tag
            .$this->_giveMeStringLength('document-format').'document-format' //
            .$this->_giveMeStringLength($mime_media_type).$mime_media_type; // value
        $this->setup->mime_media_type = 1;
    }

    public function setBinary()
    {
        $this->unsetRawText();
    }

    public function unsetRawText()
    {
        $this->setup->datatype = 'BINARY';
        $this->dataHead = '';
        $this->dataTail = '';
        $this->_putDebug(_('Unset forcing data to be interpreted as RAW TEXT'), 2);
    }

    public function setJobName($jobName = '', $absolute = false)
    {
        $this->meta->jobname = '';
        if ($jobName === '') {
            $this->meta->jobname = '';

            return true;
        }
        $postPend = date('-H:i:s-').$this->_setJobId();
        if ($absolute) {
            $postPend = '';
        }
        if (isset($this->values->jobname) && $jobName === '(PHP)') {
            $jobName = $this->values->jobname;
        }
        $this->values->jobname = $jobName;
        $jobName .= $postPend;
        $this->meta->jobname = \chr(0x42) // nameWithoutLanguage type || value-tag
            .\chr(0x00).\chr(0x08) //  name-length
            .'job-name' //  job-name || name
            .$this->_giveMeStringLength($jobName) // value-length
            .$jobName; // value
        $this->_putDebug(sprintf(_('Job name: %s'), $jobName), 2);
        $this->setup->jobname = 1;
    }

    protected function _setJobId(): string
    {
        ++$this->meta->jobid;
        $prepend = '';
        $prepend_length = 4 - \strlen($this->meta->jobid);
        for ($i = 0; $i < $prepend_length; ++$i) {
            $prepend .= '0';
        }

        return $prepend.$this->meta->jobid;
    }

    public function setUserName($username = 'PHP-SERVER')
    {
        $this->requesting_user = $username;
        $this->meta->username = '';
        if (!$username) {
            return true;
        }
        if ($username === 'PHP-SERVER' && isset($this->meta->username)) {
            return true;
        }
        $this->meta->username = \chr(0x42) // keyword type || value-tag
            .\chr(0x00).\chr(0x14) // name-length
            .'requesting-user-name'
            .$this->_giveMeStringLength($username) // value-length
            .$username;
        $this->_putDebug(sprintf(_('Username: %s'), $username), 2);
        $this->setup->username = 1;
    }

    protected function _buildValues(&$operationAttributes, &$jobAttributes, &$printerAttributes): bool
    {
        $operationAttributes = '';
        foreach ($this->operationTags as $key => $values) {
            $item = 0;
            if (array_key_exists('value', $values)) {
                foreach ($values['value'] as $item_value) {
                    if ($item === 0) {
                        $operationAttributes .=
                            $values['systag']
                            .$this->_giveMeStringLength($key)
                            .$key
                            .$this->_giveMeStringLength($item_value)
                            .$item_value;
                    } else {
                        $operationAttributes .=
                            $values['systag']
                            .$this->_giveMeStringLength('')
                            .$this->_giveMeStringLength($item_value)
                            .$item_value;
                    }
                    ++$item;
                }
            }
        }
        $jobAttributes = '';
        foreach ($this->jobTags as $key => $values) {
            $item = 0;
            if (array_key_exists('value', $values)) {
                foreach ($values['value'] as $item_value) {
                    if ($item === 0) {
                        $jobAttributes .=
                            $values['systag']
                            .$this->_giveMeStringLength($key)
                            .$key
                            .$this->_giveMeStringLength($item_value)
                            .$item_value;
                    } else {
                        $jobAttributes .=
                            $values['systag']
                            .$this->_giveMeStringLength('')
                            .$this->_giveMeStringLength($item_value)
                            .$item_value;
                    }
                    ++$item;
                }
            }
        }
        $printerAttributes = '';
        foreach ($this->printer_tags as $key => $values) {
            $item = 0;
            if (array_key_exists('value', $values)) {
                foreach ($values['value'] as $item_value) {
                    if ($item === 0) {
                        $printerAttributes .=
                            $values['systag']
                            .$this->_giveMeStringLength($key)
                            .$key
                            .$this->_giveMeStringLength($item_value)
                            .$item_value;
                    } else {
                        $printerAttributes .=
                            $values['systag']
                            .$this->_giveMeStringLength('')
                            .$this->_giveMeStringLength($item_value)
                            .$item_value;
                    }
                    ++$item;
                }
            }
        }
        reset($this->jobTags);
        reset($this->operationTags);
        reset($this->printer_tags);

        return true;
    }

    protected function _setOperationId()
    {
        ++$this->operation_id;
        $this->meta->operation_id = $this->_integerBuild($this->operation_id);
        $this->_putDebug('operation id is: '.$this->operation_id, 2);
    }

    protected function _sendHttp($post_values, $uri): bool
    {
        /*
             This function Copyright (C) 2005-2006 Thomas Harding, Manuel Lemos
           */
        $this->response_completed[] = 'no';
        unset($this->serverouptut);
        $this->_putDebug(_('Processing HTTP request'), 2);
        $this->serverOutput->headers = [];
        $this->serverOutput->body = '';
        $http = new \http_class();
        if ($this->unix) {
            $http->host = 'localhost';
        } else {
            $http->host = $this->host;
        }
        $http->with_exceptions = $this->withExceptions;
        if ($this->debugHttp) {
            $http->debug = 1;
            $http->html_debug = 0;
        } else {
            $http->debug = 0;
            $http->html_debug = 0;
        }

        $http->port = $this->port;
        $http->timeout = $this->httpTimeout;
        $http->data_timeout = $this->httpDataTimeout;
        $http->force_multipart_form_post = false;
        $http->user = $this->username;
        $http->password = $this->password;
        $arguments['RequestMethod'] = 'POST';
        $arguments['Headers'] = [
            'Content-Type' => 'application/ipp',
        ];
        $arguments['BodyStream'] = [
            [
                'Data' => $post_values['Data'],
            ],
        ];
        if (isset($post_values['File'])) {
            $arguments['BodyStream'][] = [
                'File' => $post_values['File'],
            ];
        }
        if (isset($post_values['FileType'])
            && !strcmp($post_values['FileType'], 'TEXT')
        ) {
            $arguments['BodyStream'][] = ['Data' => \chr(12)];
        }
        $arguments['RequestURI'] = $uri;
        if ($this->withExceptions && $this->handleHttpExceptions) {
            try {
                $success = $http->Open($arguments);
            } catch (httpException $e) {
                throw new ippException(
                    sprintf('http error: %s', $e->getMessage()),
                    $e->getErrno());
            }
        } else {
            $success = $http->Open($arguments);
        }
        if ($success[0] === true) {
            $success = $http->SendRequest($arguments);
            if ($success[0] === true) {
                $this->_putDebug('H T T P    R E Q U E S T :');
                $this->_putDebug('Request headers:');
                for (reset($http->request_headers), $header = 0,
                    $headerMax = \count($http->request_headers); $header < $headerMax;
                    next($http->request_headers), $header++) {
                    $header_name = key($http->request_headers);
                    if (\is_array($http->request_headers[$header_name])) {
                        foreach ($http->request_headers[$header_name] as $header_valueValue) {
                        $this->_putDebug($header_name.': '. $header_valueValue);
                    }
                    } else {
                        $this->_putDebug($header_name.': '.$http->request_headers[$header_name]);
                    }
                }
                $this->_putDebug('Request body:');
                $this->_putDebug(
                    htmlspecialchars($http->request_body)
                    .'*********** END REQUEST BODY *********'
                );
                $i = 0;
                $headers = [];
                unset($this->serverOutput->headers);
                $http->ReadReplyHeaders($headers);
                $this->_putDebug('H T T P    R E S P O N S E :');
                $this->_putDebug('Response headers:');
                for (reset($headers), $header = 0, $headerMax = \count($headers); $header < $headerMax; next($headers),
                    $header++) {
                    $header_name = key($headers);
                    if (\is_array($headers[$header_name])) {
                        foreach ($headers[$header_name] as $header_valueValue) {
                        $this->_putDebug($header_name.': '. $header_valueValue);
                        $this->serverOutput->headers[$i] =
                            $header_name.': '
                            . $header_valueValue;
                        ++$i;
                    }
                    } else {
                        $this->_putDebug($header_name.': '.$headers[$header_name]);
                        $this->serverOutput->headers[$i] =
                            $header_name
                            .': '
                            .$headers[$header_name];
                        ++$i;
                    }
                }
                $this->_putDebug("\n\nResponse body:\n");
                $this->serverOutput->body = '';
                for (; ;) {
                    $http->ReadReplyBody($body, 1024);
                    if ('' === $body) {
                        break;
                    }

                    $this->_putDebug(htmlentities($body));
                    $this->serverOutput->body .= $body;
                }
                $this->_putDebug('********* END RESPONSE BODY ********');
            }
        }
        $http->Close();

        return true;
    }

    protected function _parseServerOutput(): bool
    {
        $this->serverOutput->response = [];
        if (!$this->_parseHttpHeaders()) {
            return false;
        }
        $this->_parsing->offset = 0;
        $this->_parseIppVersion();
        $this->_parseStatusCode();
        $this->_parseRequestID();
        $this->_parseResponse();
        //devel
        $this->_putDebug(
            sprintf('***** IPP STATUS: %s ******', $this->serverOutput->status),
            4);
        $this->_putDebug('****** END OF OPERATION ****');

        return true;
    }

    protected function _parseHttpHeaders(): bool
    {
        switch ($this->serverOutput->headers[0]) {
            case 'http/1.1 200 ok: ':
                $this->serverOutput->httpstatus = 'HTTP/1.1 200 OK';
                break;

            // primitive http/1.0 for Lexmark printers (from Rick Baril)
            case 'http/1.0 200 ok: ':
                $this->serverOutput->httpstatus = 'HTTP/1.0 200 OK';
                break;

            case 'http/1.1 100 continue: ':
                $this->serverOutput->httpstatus = 'HTTP/1.1 100 CONTINUE';
                break;

            case '':
                $this->serverOutput->httpstatus = 'HTTP/1.1 000 No Response From Server';
                $this->serverOutput->status = 'HTTP-ERROR-000_NO_RESPONSE_FROM_SERVER';
                trigger_error('No Response From Server', E_USER_WARNING);
                $this->_errorLog('No Response From Server', 1);
                $this->disconnected = 1;

                return false;
                break;

            default:
                $server_response = preg_replace('/: $/', '', $this->serverOutput->headers[0]);
                #$strings = split(' ', $server_response, 3);
                $strings = explode(' ', $server_response, 3);
                $errorNumber = $strings[1];
                $string = strtoupper(str_replace(' ', '_', $strings[2]));
                trigger_error(
                    sprintf(_('server responds %s'), $server_response),
                    E_USER_WARNING);
                $this->_errorLog('server responds '.$server_response, 1);
                $this->serverOutput->httpstatus =
                    strtoupper($strings[0])
                    .' '
                    .$errorNumber
                    .' '
                    .ucfirst($strings[2]);

                $this->serverOutput->status =
                    'HTTP-ERROR-'
                    .$errorNumber
                    .'-'
                    .$string;
                $this->disconnected = 1;

                return false;
                break;
        }
        unset($this->serverOutput->headers);

        return true;
    }

    protected function _parseIppVersion()
    {
        $ippVersion =
            (\ord($this->serverOutput->body[$this->_parsing->offset]) * 256)
            + \ord($this->serverOutput->body[$this->_parsing->offset + 1]);
        switch ($ippVersion) {
            case 0x0101:
                $this->serverOutput->ipp_version = '1.1';
                break;

            default:
                $this->serverOutput->ipp_version =
                    sprintf('%u.%u (Unknown)',
                        \ord($this->serverOutput->body[$this->_parsing->offset]) * 256,
                        \ord($this->serverOutput->body[$this->_parsing->offset + 1]));
                break;
        }
        $this->_putDebug("I P P    R E S P O N S E :\n\n");
        $this->_putDebug(
            sprintf('IPP version %s%s: %s',
                \ord($this->serverOutput->body[$this->_parsing->offset]),
                \ord($this->serverOutput->body[$this->_parsing->offset + 1]),
                $this->serverOutput->ipp_version));
        $this->_parsing->offset += 2;
    }

    protected function _parseStatusCode()
    {
        $status_code =
            (\ord($this->serverOutput->body[$this->_parsing->offset]) * 256)
            + \ord($this->serverOutput->body[$this->_parsing->offset + 1]);
        $this->serverOutput->status = 'NOT PARSED';
        $this->_parsing->offset += 2;
        if (\strlen($this->serverOutput->body) < $this->_parsing->offset) {
            return false;
        }
        if ($status_code < 0x00FF) {
            $this->serverOutput->status = 'successful';
        } elseif ($status_code < 0x01FF) {
            $this->serverOutput->status = 'informational';
        } elseif ($status_code < 0x02FF) {
            $this->serverOutput->status = 'redirection';
        } elseif ($status_code < 0x04FF) {
            $this->serverOutput->status = 'client-error';
        } elseif ($status_code < 0x05FF) {
            $this->serverOutput->status = 'server-error';
        }
        switch ($status_code) {
            case 0x0000:
                $this->serverOutput->status = 'successful-ok';
                break;

            case 0x0001:
                $this->serverOutput->status = 'successful-ok-ignored-or-substituted-attributes';
                break;

            case 0x002:
                $this->serverOutput->status = 'successful-ok-conflicting-attributes';
                break;

            case 0x0400:
                $this->serverOutput->status = 'client-error-bad-request';
                break;

            case 0x0401:
                $this->serverOutput->status = 'client-error-forbidden';
                break;

            case 0x0402:
                $this->serverOutput->status = 'client-error-not-authenticated';
                break;

            case 0x0403:
                $this->serverOutput->status = 'client-error-not-authorized';
                break;

            case 0x0404:
                $this->serverOutput->status = 'client-error-not-possible';
                break;

            case 0x0405:
                $this->serverOutput->status = 'client-error-timeout';
                break;

            case 0x0406:
                $this->serverOutput->status = 'client-error-not-found';
                break;

            case 0x0407:
                $this->serverOutput->status = 'client-error-gone';
                break;

            case 0x0408:
                $this->serverOutput->status = 'client-error-request-entity-too-large';
                break;

            case 0x0409:
                $this->serverOutput->status = 'client-error-request-value-too-long';
                break;

            case 0x040A:
                $this->serverOutput->status = 'client-error-document-format-not-supported';
                break;

            case 0x040B:
                $this->serverOutput->status = 'client-error-attributes-or-values-not-supported';
                break;

            case 0x040C:
                $this->serverOutput->status = 'client-error-uri-scheme-not-supported';
                break;

            case 0x040D:
                $this->serverOutput->status = 'client-error-charset-not-supported';
                break;

            case 0x040E:
                $this->serverOutput->status = 'client-error-conflicting-attributes';
                break;

            case 0x040F:
                $this->serverOutput->status = 'client-error-compression-not-supported';
                break;

            case 0x0410:
                $this->serverOutput->status = 'client-error-compression-error';
                break;

            case 0x0411:
                $this->serverOutput->status = 'client-error-document-format-error';
                break;

            case 0x0412:
                $this->serverOutput->status = 'client-error-document-access-error';
                break;

            case 0x0413: // RFC3380
                $this->serverOutput->status = 'client-error-attributes-not-settable';
                break;

            case 0x0500:
                $this->serverOutput->status = 'server-error-internal-error';
                break;

            case 0x0501:
                $this->serverOutput->status = 'server-error-operation-not-supported';
                break;

            case 0x0502:
                $this->serverOutput->status = 'server-error-service-unavailable';
                break;

            case 0x0503:
                $this->serverOutput->status = 'server-error-version-not-supported';
                break;

            case 0x0504:
                $this->serverOutput->status = 'server-error-device-error';
                break;

            case 0x0505:
                $this->serverOutput->status = 'server-error-temporary-error';
                break;

            case 0x0506:
                $this->serverOutput->status = 'server-error-not-accepting-jobs';
                break;

            case 0x0507:
                $this->serverOutput->status = 'server-error-busy';
                break;

            case 0x0508:
                $this->serverOutput->status = 'server-error-job-canceled';
                break;

            case 0x0509:
                $this->serverOutput->status = 'server-error-multiple-document-jobs-not-supported';
                break;

            default:
                break;
        }
        $this->_putDebug(
            sprintf(
                'status-code: %s%s: %s ',
                $this->serverOutput->body[$this->_parsing->offset],
                $this->serverOutput->body[$this->_parsing->offset + 1],
                $this->serverOutput->status),
            4);
    }

    protected function _parseRequestID()
    {
        $this->serverOutput->request_id =
            $this->_interpretInteger(
                substr($this->serverOutput->body, $this->_parsing->offset, 4)
            );
        $this->_putDebug('request-id '.$this->serverOutput->request_id, 2);
        $this->_parsing->offset += 4;
    }

    protected function _interpretInteger($value): int
    {
        // they are _signed_ integers
        $value_parsed = 0;
        for ($i = \strlen($value); $i > 0; --$i) {
            $value_parsed +=
                (
                    (1 << (($i - 1) * 8))
                    *
                    \ord($value[\strlen($value) - $i])
                );
        }
        if ($value_parsed >= 2147483648) {
            $value_parsed -= 4294967296;
        }

        return $value_parsed;
    }

    protected function _parseResponse()
    {
    }
}
