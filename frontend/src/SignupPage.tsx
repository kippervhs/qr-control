import { FormEvent, useState } from "react";
import { ArrowLeft, ArrowRight, LockKey, User } from "@phosphor-icons/react";
import { api, Session, setCsrfToken } from "./api";

export default function SignupPage({ onCreated, onBack }: { onCreated: (session: Session) => void; onBack: () => void }) {
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError("");
    const form = new FormData(event.currentTarget);
    try {
      const session = await api<Session>("/api/auth/register", {
        method: "POST",
        body: JSON.stringify({
          name: form.get("name"),
          email: form.get("email"),
          username: form.get("username"),
          password: form.get("password"),
        }),
      });
      setCsrfToken(session.csrfToken);
      onCreated(session);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Não foi possível criar sua conta.");
    } finally {
      setLoading(false);
    }
  }

  return <main className="grid min-h-[100dvh] place-items-center bg-[#111] px-4 py-8">
    <section className="w-full max-w-[520px] rounded-[28px] border-2 border-[#484848] bg-[var(--surface)] p-6 text-black sm:p-8">
      <button onClick={onBack} className="inline-flex items-center gap-2 text-sm font-bold text-[#555]"><ArrowLeft size={18}/> Voltar</button>
      <h1 className="mt-6 text-3xl font-black uppercase">Criar conta</h1>
      <p className="mt-2 text-sm font-medium text-[#555]">Crie sua conta para começar a usar o QR Control.</p>
      <form onSubmit={submit} className="mt-7 space-y-4" noValidate>
        <label className="block space-y-2"><span className="text-sm font-bold">Nome</span><input name="name" autoComplete="name" required className="min-h-12 w-full rounded-[16px] border-2 border-[#777] bg-white px-4"/></label>
        <label className="block space-y-2"><span className="text-sm font-bold">E-mail</span><input name="email" type="email" autoComplete="email" required className="min-h-12 w-full rounded-[16px] border-2 border-[#777] bg-white px-4"/></label>
        <label className="block space-y-2"><span className="text-sm font-bold">Usuário</span><span className="relative block"><User className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#4b4b4b]" size={20}/><input name="username" autoComplete="username" required className="min-h-12 w-full rounded-[16px] border-2 border-[#777] bg-white pl-11 pr-4"/></span></label>
        <label className="block space-y-2"><span className="text-sm font-bold">Senha</span><span className="relative block"><LockKey className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#4b4b4b]" size={20}/><input name="password" type="password" minLength={8} autoComplete="new-password" required className="min-h-12 w-full rounded-[16px] border-2 border-[#777] bg-white pl-11 pr-4"/></span></label>
        {error && <p className="rounded-[16px] bg-[#f5c9c6] px-3 py-2.5 text-sm font-bold text-[#8f1f18]">{error}</p>}
        <button disabled={loading} className="action-label pressable flex min-h-14 w-full items-center justify-center gap-2 rounded-full bg-[var(--accent)] px-4 font-black uppercase disabled:opacity-60">{loading ? "Criando..." : "Criar conta"}<ArrowRight size={19} weight="bold"/></button>
      </form>
    </section>
  </main>;
}
