import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Pencil, Plus } from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

const emptyPlan = {
    name: '', description: '', price: '0', billing_interval: 'monthly', trial_days: '30',
    student_limit: '', teacher_limit: '', class_limit: '', features: '', is_active: true,
};

export default function PlansIndex({ user, plans }) {
    const [editing, setEditing] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm(emptyPlan);

    const submit = (event) => {
        event.preventDefault();
        if (editing) {
            put(route('plans.update', editing.id), { onSuccess: closeForm });
            return;
        }
        post(route('plans.store'), { onSuccess: closeForm });
    };

    const closeForm = () => { setEditing(null); reset(); };
    const startEdit = (plan) => {
        setEditing(plan);
        setData({
            name: plan.name, description: plan.description || '', price: plan.price,
            billing_interval: plan.billing_interval, trial_days: String(plan.trial_days),
            student_limit: plan.student_limit || '', teacher_limit: plan.teacher_limit || '',
            class_limit: plan.class_limit || '', features: plan.features || '', is_active: plan.is_active,
        });
        document.getElementById('plan-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    const remove = (plan) => router.delete(route('plans.destroy', plan.id));

    return (
        <AdminLayout user={user} title="Plans & Packages" onLogout={() => router.post(route('logout.web'))}>
            <Head title="Plans & Packages" />
            <div className="grid gap-6 xl:grid-cols-[1fr_400px]">
                <Card size="sm" className="gap-0 py-0">
                    <CardHeader className="border-b px-5 py-4">
                        <CardTitle>Available Plans</CardTitle>
                        <CardDescription>{plans.length} plan{plans.length === 1 ? '' : 's'} configured.</CardDescription>
                    </CardHeader>
                    {plans.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/50 hover:bg-muted/50">
                                    <TableHead className="px-5">Plan</TableHead>
                                    <TableHead className="px-4">Price</TableHead>
                                    <TableHead className="px-4">Limits</TableHead>
                                    <TableHead className="px-4">Status</TableHead>
                                    <TableHead className="px-4 text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {plans.map((plan) => (
                                    <TableRow key={plan.id}>
                                        <TableCell className="px-5 py-4">
                                            <p className="font-medium">{plan.name}</p>
                                            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{plan.description || 'No description'}</p>
                                        </TableCell>
                                        <TableCell className="px-4 py-4">
                                            <p className="font-medium tabular-nums">{plan.price}</p>
                                            <p className="text-xs capitalize text-muted-foreground">per {plan.billing_interval === 'monthly' ? 'month' : 'year'}</p>
                                        </TableCell>
                                        <TableCell className="px-4 py-4">
                                            <div className="space-y-1 text-xs text-muted-foreground">
                                                <p>Students: <span className="font-medium text-foreground">{plan.student_limit || 'Unlimited'}</span></p>
                                                <p>Teachers: <span className="font-medium text-foreground">{plan.teacher_limit || 'Unlimited'}</span></p>
                                                <p>Classes: <span className="font-medium text-foreground">{plan.class_limit || 'Unlimited'}</span></p>
                                            </div>
                                        </TableCell>
                                        <TableCell className="px-4 py-4">
                                            <Badge variant="secondary" className={plan.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'text-muted-foreground'}>
                                                {plan.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="px-4 py-4 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button type="button" variant="ghost" size="icon-sm" title="Edit" onClick={() => startEdit(plan)}>
                                                    <Pencil className="size-3.5" />
                                                </Button>
                                                <AlertDialog>
                                                    <AlertDialogTrigger asChild>
                                                        <Button type="button" variant="ghost" size="icon-sm" title="Delete" className="text-destructive hover:text-destructive">
                                                            <svg xmlns="http://www.w3.org/2000/svg" className="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                        </Button>
                                                    </AlertDialogTrigger>
                                                    <AlertDialogContent>
                                                        <AlertDialogHeader>
                                                            <AlertDialogTitle>Delete {plan.name}?</AlertDialogTitle>
                                                            <AlertDialogDescription>
                                                                This plan will be permanently removed. This action cannot be undone.
                                                            </AlertDialogDescription>
                                                        </AlertDialogHeader>
                                                        <AlertDialogFooter>
                                                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                            <AlertDialogAction variant="destructive" onClick={() => remove(plan)}>Delete</AlertDialogAction>
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
                        <div className="px-6 py-16 text-center">
                            <p className="text-sm font-medium">No plans yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">Create your first plan, then assign it to an institute.</p>
                        </div>
                    )}
                </Card>

                <form id="plan-form" onSubmit={submit} className="h-fit">
                    <Card size="sm" className="gap-0 py-0">
                        <CardHeader className="border-b px-5 py-4">
                            <CardTitle className="flex items-center gap-2">
                                {editing ? <Pencil className="size-4.5 text-muted-foreground" /> : <Plus className="size-4.5 text-muted-foreground" />}
                                {editing ? 'Edit Plan' : 'New Plan'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-5 pb-5 pt-4">
                            {editing && (
                                <Button type="button" variant="outline" size="sm" onClick={closeForm} className="mb-3">
                                    Cancel editing
                                </Button>
                            )}

                            <div className="space-y-3">
                                <Field label="Plan name" error={errors.name}>
                                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="e.g. Pro" />
                                </Field>
                                <Field label="Description" error={errors.description}>
                                    <Textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} />
                                </Field>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Price" error={errors.price}>
                                        <Input type="number" min="0" step="0.01" value={data.price} onChange={(e) => setData('price', e.target.value)} required />
                                    </Field>
                                    <Field label="Billing" error={errors.billing_interval}>
                                        <Select value={data.billing_interval} onValueChange={(value) => setData('billing_interval', value)}>
                                            <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="monthly">Monthly</SelectItem>
                                                <SelectItem value="yearly">Yearly</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>
                                <Field label="Trial days" error={errors.trial_days}>
                                    <Input type="number" min="0" value={data.trial_days} onChange={(e) => setData('trial_days', e.target.value)} required />
                                </Field>
                                <div className="grid grid-cols-3 gap-3">
                                    <Field label="Students" error={errors.student_limit}>
                                        <Input type="number" min="1" value={data.student_limit} onChange={(e) => setData('student_limit', e.target.value)} placeholder="∞" />
                                    </Field>
                                    <Field label="Teachers" error={errors.teacher_limit}>
                                        <Input type="number" min="1" value={data.teacher_limit} onChange={(e) => setData('teacher_limit', e.target.value)} placeholder="∞" />
                                    </Field>
                                    <Field label="Classes" error={errors.class_limit}>
                                        <Input type="number" min="1" value={data.class_limit} onChange={(e) => setData('class_limit', e.target.value)} placeholder="∞" />
                                    </Field>
                                </div>
                                <Field label="Features" error={errors.features}>
                                    <Textarea value={data.features} onChange={(e) => setData('features', e.target.value)} placeholder="Attendance, Fees, Timetable..." rows={3} />
                                </Field>
                                <label className="flex items-center gap-2.5 rounded-lg border p-3">
                                    <Checkbox checked={data.is_active} onCheckedChange={(checked) => setData('is_active', Boolean(checked))} />
                                    <span className="text-sm font-medium text-foreground">Active plan</span>
                                    <span className="ml-auto text-xs text-muted-foreground">Visible to institutes</span>
                                </label>
                            </div>

                            <Button type="submit" disabled={processing} className="mt-5 w-full" size="lg">
                                {processing ? 'Saving...' : editing ? 'Update Plan' : 'Create Plan'}
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AdminLayout>
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
