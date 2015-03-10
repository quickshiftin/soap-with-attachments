# soap-with-attachments
PHP SoapClient supporting SOAP with Attachments (SwA)

## Warning: This is example code, not a drop in library
### You will need to mofidy it to your needs.

## Overview

A while back I had to interact with a [PortaOne VOIP system](http://portaone.com/) via SOAP. In order to upload and download audio files (.au no less) I needed to support [SOAP with Attachments](http://en.wikipedia.org/wiki/SOAP_with_Attachments). By the time I'd gotten to the audio piece I already had a full-fledged client library based around PHP's [SoapClient](http://php.net/manual/en/class.soapclient.php). There was a solution available using NuSOAP however, I wasn't about to have one disheveled little turd amidst my shiny SOAPClient library!

That said I set out to implement SwA via **native** methods in PHP5. I pulled it off, however the code I have and the code shared here is very much hardcoded for my original purpose. The PortaOne system is built on top of [Perl's SOAP::Lite module](http://search.cpan.org/~phred/SOAP-Lite-1.13/lib/SOAP/Lite.pm). It's no surprise that there are options left to the implementation in [the SwA protocol](http://www.w3.org/TR/SOAP-attachments) so besides the hardcoding in this library it's not at all ready for general use...

## Article

I wrote [an article](https://quickshiftin.com/blog/2013/09/soap-client-attachments-php/) that goes more in depth than the brief here.
