import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';import { Building2, Camera, Pencil, Plus, Trash2, Users } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';

const emptyInstitute = {
    name: '',
    email: '',
    phone: '',
    address: '',
    logo: null,
    favicon: null,
    attendance_mode: 'class',
    is_active: true,
    // User fields
    user_name: '',
    user_email: '',
    user_phone: '',
    user_password: '',
    plan_id: '',
    subscription_status: 'trialing',
};

export default function InstitutesIndex({ user, institutes, plans }) {
    const [editing, setEditing] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm(emptyInstitute);

    function submit(event) {
        event.preventDefault();

        if (editing) {
            router.post(route('institute.update', editing.public_id), {
                _method: 'put',
                ...data,
            }, {
                onSuccess: closeForm,
                forceFormData: true,
            });
            return;
        }

        post(route('institute.store'), {
            onSuccess: closeForm,
            forceFormData: true,
        });
    }

    function startEdit(institute) {
        setEditing(institute);
        setData({
            name: institute.name || '',
            email: institute.email || '',
            phone: institute.phone || '',
            address: institute.address || '',
            // File inputs cannot be pre-populated. Keep existing uploads unless a
            // replacement file is selected.
            logo: null,
            favicon: null,
            attendance_mode: institute.attendance_mode || 'class',
            is_active: institute.is_active ?? true,
            user_name: institute.owner?.name || '',
            user_email: institute.owner?.email || '',
            user_phone: institute.owner?.phone || '',
            user_password: '',
            plan_id: institute.subscription?.plan_id ? String(institute.subscription.plan_id) : '',
            subscription_status: institute.subscription?.status || 'trialing',
        });
        document.getElementById('institute-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function closeForm() {
        setEditing(null);
        reset();
    }

    function remove(institute) {
        router.delete(route('institute.destroy', institute.public_id));
    }

    return (
        <AdminLayout user={user} title="Institutes" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Institutes" />
            <div className="grid gap-6 xl:grid-cols-[1fr_380px]">
                <Card size="sm" className="gap-0 py-0">
                    <CardHeader className="border-b px-5 py-4">
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="size-4.5 text-muted-foreground" />
                            All Institutes
                        </CardTitle>
                        <CardDescription>
                            {institutes.length} institute{institutes.length === 1 ? '' : 's'} registered on the platform.
                        </CardDescription>
                    </CardHeader>
                    {institutes.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead className="px-5">Institute</TableHead>
                                    <TableHead className="px-4">Plan</TableHead>
                                    <TableHead className="px-4">Invoice</TableHead>
                                    <TableHead className="px-4">Status</TableHead>
                                    <TableHead className="px-4 text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {institutes.map((institute) => (
                                    <TableRow key={institute.public_id}>
                                        <TableCell className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <InstituteAvatar institute={institute} />
                                                <div className="min-w-0 leading-tight">
                                                    <p className="truncate font-medium text-foreground">{institute.name}</p>
                                                    <p className="mt-0.5 truncate text-xs text-muted-foreground">{institute.email || 'No email'}</p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="px-4 py-4">
                                            <div className="leading-tight">
                                                <p className="text-sm font-medium">{institute.subscription?.plan?.name || '—'}</p>
                                                {institute.subscription && (
                                                    <p className="mt-0.5 text-xs capitalize text-muted-foreground">{institute.subscription.status}</p>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="px-4 py-4">
                                            <InvoiceSummary invoice={institute.subscription?.invoice} />
                                        </TableCell>
                                        <TableCell className="px-4 py-4">
                                            <Badge variant={institute.is_active ? 'secondary' : 'outline'} className={institute.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'text-muted-foreground'}>
                                                {institute.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="px-4 py-4 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button type="button" variant="ghost" size="icon-sm" title="Edit" onClick={() => startEdit(institute)}>
                                                    <Pencil className="size-3.5" />
                                                </Button>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button type="button" variant="ghost" size="icon-sm" title="Delete" className="text-destructive hover:text-destructive">
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>Delete {institute.name}?</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This will permanently remove the institute and its data. This action cannot be undone.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction variant="destructive" onClick={() => remove(institute)}>
                                                                Delete
                                                            </AlertDialogAction>
                                                        </AlertDialogFooter>
                                                    </AlertDialogContent>
                                                </AlertDialog>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <div className="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Building2 className="size-6 text-muted-foreground" />
                            </div>
                            <div>
                                <p className="text-sm font-medium">No institutes yet</p>
                                <p className="mt-1 text-sm text-muted-foreground">Add your first institute using the form on the right.</p>
                            </div>
                        </div>
                    )}
                </Card>

                <form id="institute-form" onSubmit={submit} className="h-fit">
                    <Card size="sm" className="gap-0 py-0">
                        <CardHeader className="border-b px-5 py-4">
                            <CardTitle className="flex items-center gap-2">
                                {editing ? <Pencil className="size-4.5 text-muted-foreground" /> : <Plus className="size-4.5 text-muted-foreground" />}
                                {editing ? 'Edit Institute' : 'New Institute'}
                            </CardTitle>
                            {editing && (
                                <CardDescription>Editing <span className="font-medium text-foreground">{editing.name}</span></CardDescription>
                            )}
                        </CardHeader>
                        <CardContent className="px-5 pb-5 pt-4">
                            {editing && (
                                <Button type="button" variant="outline" size="sm" onClick={closeForm} className="mb-3">
                                    Cancel editing
                                </Button>
                            )}

                            <div className="space-y-3">
                                <Field label="Name" error={errors.name}>
                                    <Input value={data.name} onChange={(event) => setData('name', event.target.value)} required placeholder="e.g. City Grammar School" />
                                </Field>
                                <Field label="Email" error={errors.email}>
                                    <Input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} placeholder="admin@school.com" />
                                </Field>
                                <Field label="Phone" error={errors.phone}>
                                    <Input value={data.phone} onChange={(event) => setData('phone', event.target.value)} placeholder="+92 300 0000000" />
                                </Field>
                                <Field label="Address" error={errors.address}>
                                    <Textarea value={data.address} onChange={(event) => setData('address', event.target.value)} rows={2} />
                                </Field>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Logo" error={errors.logo}>
                                        <Input type="file" accept="image/*" onChange={(event) => setData('logo', event.target.files[0] || null)} />
                                    </Field>
                                    <Field label="Favicon" error={errors.favicon}>
                                        <Input type="file" accept="image/*" onChange={(event) => setData('favicon', event.target.files[0] || null)} />
                                    </Field>
                                </div>
                                <Field label="Attendance mode" error={errors.attendance_mode}>
                                    <Select value={data.attendance_mode} onValueChange={(value) => setData('attendance_mode', value)}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="class">Class</SelectItem>
                                            <SelectItem value="subject">Subject</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>
                            </div>

                            <Separator className="my-5" />

                            <h3 className="mb-3 flex items-center gap-2 text-sm font-medium">
                                <Users className="size-4 text-muted-foreground" />
                                Institute User
                            </h3>
                            <div className="space-y-3">
                                <Field label="User Name" error={errors.user_name}>
                                    <Input value={data.user_name} onChange={(event) => setData('user_name', event.target.value)} placeholder="Same as institute name" />
                                </Field>
                                <Field label="User Email" error={errors.user_email}>
                                    <Input type="email" value={data.user_email} onChange={(event) => setData('user_email', event.target.value)} placeholder="Same as institute email" />
                                </Field>
                                <Field label="User Phone" error={errors.user_phone}>
                                    <Input value={data.user_phone} onChange={(event) => setData('user_phone', event.target.value)} placeholder="Same as institute phone" />
                                </Field>
                                <Field label={editing ? 'New Password (optional)' : 'User Password'} error={errors.user_password}>
                                    <Input type="password" value={data.user_password} onChange={(event) => setData('user_password', event.target.value)} required={!editing} />
                                </Field>
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="mt-5 w-full"
                                size="lg"
                            >
                                {processing ? 'Saving...' : editing ? 'Update Institute' : 'Create Institute'}
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AdminLayout>
    );
}

function InstituteAvatar({ institute }) {
    const logoSrc = institute.logo_url || (institute.logo ? (institute.logo.startsWith('http://') || institute.logo.startsWith('https://') || institute.logo.startsWith('/') ? institute.logo : `/storage/${institute.logo}`) : null);

    if (logoSrc) {
        return (
            <img
                src={logoSrc}
                alt="logo"
                className="size-10 rounded-lg object-cover ring-1 ring-foreground/10"
                onError={(e) => { e.currentTarget.style.display = 'none'; }}
            />
        );
    }

    return (
        <div className="flex size-10 items-center justify-center rounded-lg bg-muted text-muted-foreground">
            <Camera className="size-4" />
        </div>
    );
}

function InvoiceSummary({ invoice }) {
    if (!invoice) {
        return <span className="text-xs text-muted-foreground">No invoice yet</span>;
    }

    const badgeVariant = {
        paid: 'default',
        verification_pending: 'secondary',
        open: 'outline',
        void: 'ghost',
    }[invoice.status] || 'outline';

    return (
        <div className="leading-tight">
            <p className="font-mono text-xs font-medium text-foreground">{invoice.invoice_number}</p>
            <p className="mt-1 text-xs text-muted-foreground">PKR {invoice.amount}</p>
            <div className="mt-1.5 flex items-center gap-2">
                <Badge variant={badgeVariant} className="text-[0.65rem] capitalize">{invoice.status.replace('_', ' ')}</Badge>
                {invoice.status === 'verification_pending' && (
                    <Button type="button" size="xs" variant="outline" className="text-emerald-600 dark:text-emerald-400"
                        onClick={() => router.put(route('subscription-invoices.verify', invoice.id))}>
                        Verify
                    </Button>
                )}
            </div>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <div className="space-y-1.5">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-xs font-medium text-destructive">{error}</p>}
        </div>
    );
}
