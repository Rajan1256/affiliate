<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
</head>
<body>
	<div>
	    Dear {{ $firstname }} {{ $lastname }},
	    <br>
	    <br>
		Royal has created an Affiliate account in your name. Please <a href="{{ $url }}">click here</a> to login using your email. 
	    <br>
		Your password is: {{ $password }}
	    <br>
	</div>
</body>
</html>