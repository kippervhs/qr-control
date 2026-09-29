import { FormEvent, useEffect, useMemo, useState } from "react";
import { Check, MagnifyingGlass, Plus, Trash, UserCircle, UsersThree, X } from "@phosphor-icons/react";
import { toast } from "sonner";
import { api } from "./api";
import type { User } from "./types";

const labels = { admin: "Administrador", vendedor: "Vendedor", manutencao: "Manutenção" } as const;

export default function UsersPage({ actorRole }: { actorRole: "admin" | "manutencao" }) {
  const canChangeRole = true;
  const canCreateAdmin = actorRole === "admin";
  const [users, setUsers] = useState<User[] | null>(null);
  const [busy, setBusy] = useState("");
  const [search, setSearch] = useState("");
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState({ username: "", password: "", confirmPassword: "", role: "vendedor" as User["role"] });

  const load = () => api<{ items: User[] }>("/api/users").then(r => setUsers(r.items)).catch(e => toast.error(e.message));
  useEffect(() => { load(); }, []);

  const filteredUsers = useMemo(() => {
    const term = search.trim().toLowerCase().replace(/^@/, "");
    if (!term) return users ?? [];
    return (users ?? []).filter(user => user.username.toLowerCase().includes(term));
  }, [users, search]);

  function closeModal() {
    if (busy === "create") return;
    setModalOpen(false);
    setForm({ username: "", password: "", confirmPassword: "", role: "vendedor" });
  }

  async function create(e: FormEvent) {
    e.preventDefault();
    if (form.password !== form.confirmPassword) {
      toast.error("As senhas não coincidem.");
      return;
    }
    setBusy("create");
    try {
      await api("/api/users", {
        method: "POST",
        body: JSON.stringify({ username: form.username, password: form.password, role: form.role }),
      });
      toast.success("Usuário criado.");
      closeModal();
      load();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Falha ao criar usuário.");
    } finally {
      setBusy("");
    }
  }

  async function patch(user: User, body: Record<string, unknown>) {
    setBusy(user.id);
    try {
      await api<User>(`/api/users/${user.id}`, { method: "PATCH", body: JSON.stringify(body) });
      toast.success("Usuário atualizado.");
      load();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Falha ao atualizar usuário.");
    } finally {
      setBusy("");
    }
  }

  async function remove(user: User) {
    if (!confirm(`Excluir @${user.username}? Esta ação não pode ser desfeita.`)) return;
    setBusy(user.id);
    try {
      await api(`/api/users/${user.id}`, { method: "DELETE" });
      toast.success("Usuário excluído.");
      load();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Falha ao excluir usuário.");
    } finally {
      setBusy("");
    }
  }

  return <div>
    <div className="flex flex-col gap-2">
      <h1 className="text-[1.7rem] font-black uppercase leading-none sm:text-[2rem] 2xl:text-[2.45rem]">Usuários</h1>
      <p className="text-base font-semibold text-[var(--ink-muted)] sm:text-xl">Contas, classes e acesso ao sistema.</p>
    </div>

    <div className="mt-7 flex flex-col gap-3 sm:flex-row sm:items-center">
      <label className="relative block min-w-0 flex-1 sm:max-w-[460px]">
        <MagnifyingGlass size={21} weight="bold" className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#555]" />
        <input
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Pesquisar usuário..."
          aria-label="Pesquisar usuário"
          className="min-h-12 w-full rounded-full border-[3px] border-[var(--line)] bg-[var(--surface)] pl-11 pr-11 font-bold text-black outline-none transition focus:border-[#555]"
        />
        {search && <button type="button" onClick={() => setSearch("")} aria-label="Limpar pesquisa" className="absolute right-3 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-full text-[#555] hover:bg-[#ddd]"><X size={17} weight="bold" /></button>}
      </label>
      <button type="button" onClick={() => setModalOpen(true)} className="action-label inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-[var(--accent)] px-6 font-black uppercase text-black">
        <Plus size={20} weight="bold" /> Criar usuário
      </button>
    </div>

    <section className="mt-5 overflow-hidden rounded-[22px] border-[3px] border-[var(--line)] bg-[var(--surface)] text-black">
      <div className="flex items-center justify-between gap-3 border-b-2 border-[#bbb] px-4 py-4 sm:px-5">
        <div className="flex items-center gap-3"><UsersThree size={26} /><h2 className="text-xl font-black uppercase">Contas cadastradas</h2></div>
        {users && <span className="rounded-full bg-[#e9e9e9] px-3 py-1 text-xs font-black">{filteredUsers.length}{search ? ` / ${users.length}` : ""}</span>}
      </div>

      {!users ? <div className="p-8 text-center text-[#4f4f4f]">Carregando...</div> :
        filteredUsers.length === 0 ? <div className="p-10 text-center"><MagnifyingGlass size={32} className="mx-auto text-[#777]" /><p className="mt-2 font-bold text-[#555]">{search ? `Nenhum usuário encontrado para “${search}”.` : "Nenhuma conta cadastrada."}</p></div> :
        <div>{filteredUsers.map(user => <article key={user.id} className="border-b border-[#bbb] p-4 last:border-b-0 sm:p-5">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex min-w-0 items-center gap-3">
              <span className="grid size-11 shrink-0 place-items-center rounded-full bg-[#333] text-white"><UserCircle size={27} /></span>
              <div className="min-w-0"><b className="block truncate text-lg font-black">@{user.username}</b><span className="text-sm text-[#555]">{user.codePrefix} · criado em {new Date(user.createdAt.replace(" ", "T") + "Z").toLocaleDateString("pt-BR")}</span></div>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <span className={`rounded-full px-3 py-1 text-xs font-black uppercase ${user.active ? "bg-[#d7efd9] text-[#176322]" : "bg-[#eee] text-[#666]"}`}>{user.active ? "Ativado" : "Desativado"}</span>
              {canChangeRole && user.role !== "admin" ? <select value={user.role} onChange={e => patch(user, { role: e.target.value })} disabled={busy === user.id} className="min-h-10 rounded-full border-2 border-[#777] bg-white px-3 text-sm font-bold">{Object.entries(labels).filter(([value]) => canCreateAdmin || value !== "admin").map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select> : <span className="rounded-full bg-[#eee] px-3 py-2 text-sm font-bold">{labels[user.role]}</span>}
              <button disabled={busy === user.id || user.role === "admin"} onClick={() => patch(user, { active: !user.active })} title={user.active ? "Inativar" : "Ativar"} className="grid size-10 place-items-center rounded-full border-2 border-[#777] bg-white disabled:cursor-not-allowed disabled:opacity-40">{user.active ? <X size={18} /> : <Check size={18} />}</button>
              <button disabled={busy === user.id || user.role === "admin"} onClick={() => remove(user)} title="Excluir" className="grid size-10 place-items-center rounded-full bg-[#d9362b] text-white disabled:cursor-not-allowed disabled:opacity-40"><Trash size={18} /></button>
            </div>
          </div>
        </article>)}</div>
      }
    </section>

    {modalOpen && <div className="fixed inset-0 z-50 grid place-items-center bg-black/65 p-4" role="dialog" aria-modal="true" aria-labelledby="create-user-title" onMouseDown={e => { if (e.target === e.currentTarget) closeModal(); }}>
      <form onSubmit={create} className="w-full max-w-[560px] rounded-[24px] border-[3px] border-[var(--line)] bg-[var(--surface)] p-5 text-black shadow-2xl sm:p-7">
        <div className="flex items-start justify-between gap-4">
          <div><h2 id="create-user-title" className="text-2xl font-black uppercase">Criar usuário</h2><p className="mt-1 text-sm font-semibold text-[#555]">{canCreateAdmin ? "Defina os dados e a classe desta conta." : "A manutenção pode criar usuários Vendedor ou Manutenção."}</p></div>
          <button type="button" onClick={closeModal} disabled={busy === "create"} aria-label="Fechar" className="grid size-10 shrink-0 place-items-center rounded-full border-2 border-[#777] bg-white"><X size={20} weight="bold" /></button>
        </div>

        <div className="mt-6 grid gap-4">
          <label className="grid gap-1.5 text-sm font-black uppercase">Nome de usuário
            <input autoFocus required minLength={3} maxLength={100} pattern="[A-Za-z0-9_]+" value={form.username} onChange={e => setForm({ ...form, username: e.target.value })} placeholder="Ex.: joao_silva" className="min-h-12 rounded-[15px] border-2 border-[#777] bg-white px-4 font-semibold outline-none focus:border-[#222]" />
          </label>
          <div className="grid gap-4 sm:grid-cols-2">
            <label className="grid gap-1.5 text-sm font-black uppercase">Senha
              <input required minLength={8} type="password" value={form.password} onChange={e => setForm({ ...form, password: e.target.value })} placeholder="Mín. 8 caracteres" className="min-h-12 rounded-[15px] border-2 border-[#777] bg-white px-4 font-semibold outline-none focus:border-[#222]" />
            </label>
            <label className="grid gap-1.5 text-sm font-black uppercase">Confirmar senha
              <input required minLength={8} type="password" value={form.confirmPassword} onChange={e => setForm({ ...form, confirmPassword: e.target.value })} placeholder="Repita a senha" className="min-h-12 rounded-[15px] border-2 border-[#777] bg-white px-4 font-semibold outline-none focus:border-[#222]" />
            </label>
          </div>
          <label className="grid gap-1.5 text-sm font-black uppercase">Classe
            <select value={form.role} onChange={e => setForm({ ...form, role: e.target.value as User["role"] })} className="min-h-12 rounded-[15px] border-2 border-[#777] bg-white px-4 font-semibold outline-none focus:border-[#222]"><option value="vendedor">Vendedor</option><option value="manutencao">Manutenção</option>{canCreateAdmin && <option value="admin">Administrador</option>}</select>
          </label>
        </div>

        <div className="mt-7 flex justify-end gap-2">
          <button type="button" onClick={closeModal} disabled={busy === "create"} className="min-h-11 rounded-full border-2 border-[#777] bg-white px-5 font-black uppercase">Cancelar</button>
          <button type="submit" disabled={busy === "create"} className="min-h-11 rounded-full bg-[var(--accent)] px-6 font-black uppercase">{busy === "create" ? "Criando..." : "Criar usuário"}</button>
        </div>
      </form>
    </div>}
  </div>;
}
