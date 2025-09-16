// resources/js/useShared.ts (if using TS)
import { usePage } from '@inertiajs/react';

type AuthUser = { id: number; name: string; email: string }; // email required to match existing PageProps
type Tenant   = { id: number; name?: string; business_name?: string };

type SharedProps = {
  auth: { user: AuthUser | null; abilities?: { viewReports?: boolean } };
  tenant: Tenant | null;
  flash: { success?: string; error?: string; message?: string };
  // ziggy optional if you call route() on the client
};

export default function useShared() {
  return usePage<{ auth: SharedProps['auth']; tenant: SharedProps['tenant']; flash: SharedProps['flash'] }>().props;
}
