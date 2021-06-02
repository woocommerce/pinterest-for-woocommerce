export const isConnected = ( appSettings ) => {
	return undefined === appSettings
		? undefined
		: !! appSettings?.token?.access_token;
};

export const isDomainVerified = ( appSettings ) => {
	return undefined === appSettings
		? undefined
		: undefined === appSettings?.account_data?.verified_domains
		? false
		: appSettings?.account_data?.verified_domains.includes(
				wcSettings.pin4wc.domainToVerify
		  );
};

export const isTrackingConfigured = ( appSettings ) => {
	return undefined === appSettings
		? undefined
		: !! ( appSettings?.tracking_advertiser && appSettings?.tracking_tag );
};
