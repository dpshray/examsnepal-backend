Welcome {{ ucwords($name) }}! 
you can verify this email by visiting this <a href="{{ html_entity_decode($url) }}" title="">link</a>
(this link expires at {{ $expiration_period }})