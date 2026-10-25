import { ApiEnvelope, ApiResponse } from '../types';

const API_ENDPOINT = '/api/index.php';

export class ApiService {
  private static authToken: string | null = localStorage.getItem('omnipos_token');
  private static terminalId: string = localStorage.getItem('omnipos_terminal_id') || 'TERM-01';
  private static onLicenseErrorCallback: ((err: any) => void) | null = null;

  public static setAuthToken(token: string | null): void {
    this.authToken = token;
    if (token) {
      localStorage.setItem('omnipos_token', token);
    } else {
      localStorage.removeItem('omnipos_token');
    }
  }

  public static getAuthToken(): string | null {
    return this.authToken;
  }

  public static setTerminalId(terminalId: string): void {
    this.terminalId = terminalId;
    localStorage.setItem('omnipos_terminal_id', terminalId);
  }

  public static getTerminalId(): string {
    return this.terminalId;
  }

  public static setLicenseErrorCallback(cb: (err: any) => void): void {
    this.onLicenseErrorCallback = cb;
  }

  /**
   * 100% HTTP POST Protocol Request
   */
  public static async request<T = any>(action: string, payload: any = {}): Promise<ApiResponse<T>> {
    const envelope: ApiEnvelope = {
      action,
      terminal_id: this.terminalId,
      client_timestamp: Math.floor(Date.now() / 1000),
      auth_token: this.authToken,
      payload,
    };

    try {
      const response = await fetch(API_ENDPOINT, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Terminal-Id': this.terminalId,
        },
        body: JSON.stringify(envelope),
      });

      const data: ApiResponse<T> = await response.json();

      // Check license or clock errors
      if (!data.success && data.error) {
        const errCode = data.error.code;
        if (
          errCode === 'LICENSE_EXPIRED' ||
          errCode === 'CLOCK_TAMPER_DETECTED' ||
          errCode === 'CLOCK_HMAC_INVALID' ||
          errCode === 'LICENSE_NOT_FOUND' ||
          errCode === 'LICENSE_TAMPERED'
        ) {
          if (this.onLicenseErrorCallback) {
            this.onLicenseErrorCallback(data.error);
          }
        }
      }

      return data;
    } catch (networkError: any) {
      // Return structured network error for offline sync detection
      return {
        success: false,
        message: networkError.message || 'Network connection failed',
        data: null as any,
        error: {
          code: 'NETWORK_OFFLINE',
          message: 'Unable to reach OmniPOS server. You may be offline.',
          details: networkError,
        },
        code: 0,
        timestamp: Math.floor(Date.now() / 1000),
      };
    }
  }
}
