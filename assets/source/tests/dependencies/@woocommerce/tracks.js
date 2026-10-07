/**
 * External dependencies
 */
import { vi } from 'vitest';

export const recordEvent = vi.fn().mockName( 'recordEvent' );

export default recordEvent;
