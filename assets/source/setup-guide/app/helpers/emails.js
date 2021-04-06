export const isEmailsListValid = emails => {

	if ( ! emails ) {
		return true;
	}

	emails = emails.split( ',' );

	// Check for wrong emails
	const wrongEmails = emails
		.map( email => email.trim() )
		.filter( email => ! isEmail( email ) );

	if ( 0 < wrongEmails.length ) {
		return false;
	}

	return true;
}

export const sanitizeEmailsList = ( emails, format = 'string' ) => {

	if ( ! emails ) {
		return;
	}

	emails = emails.split( ',' );

	// Sanitize list
	emails = emails
		.map( email => email.trim() )
		.filter( email => isEmail( email ) );

	if ( 'array' !== format ) {
		emails = emails.join( ', ' );
	}

	return emails;
}

const isEmail = email => {
	return ( /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/.test( email ) );
}
