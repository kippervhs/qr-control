import { useState } from "react";
import { ArrowRight, LockKey } from "@phosphor-icons/react";
import { api, Subscription } from "./api";

export default function SubscriptionPage({ subscription, onRefresh }: { subscription: Subscription; onRefresh: () => Promise<void> }) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  async function subscribe() {
    setLoading(true);
    setError("");
    try {
      const result = await api<{ checkoutUrl: string }>("/api/billing/checkout", { method: "POST", body: JSON.stringify({}) });
      window.location.assign(result.checkoutUrl);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Não foi possível iniciar o pagamento.");
      await onRefresh();
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="grid min-h-[100dvh] place-items-center bg-[#111] px-4 py-8">
      <section className="w-full max-w-[520px] rounded-[28px] border-2 border-[#454545] bg-[var(--surface)] p-6 text-black sm:p-9">
        <div className="flex items-center gap-3"><span className="grid size-11 place-items-center rounded-full bg-[#222] text-white"><LockKey size={22}/></span><b className="text-xl font-black">QR Control</b></div>
        <p className="mt-8 text-sm font-black uppercase text-[#c43a08]">Assinatura necessária</p>
        <h1 className="mt-2 text-3xl font-black uppercase leading-tight">Libere seu acesso</h1>
        <p className="mt-3 text-[#4f4f4f]">Seu painel e seus QR Codes ficam disponíveis enquanto a assinatura estiver ativa.</p>
        <div className="mt-7 rounded-[22px] border-2 border-[#b8b8b8] bg-[#eee] p-5">
          <p className="text-sm font-bold text-[#555]">Plano único</p>
          <p className="mt-1 text-4xl font-black">R$ 19,90<span className="text-base font-bold">/mês</span></p>
          <p className="mt-2 text-sm text-[#555]">Pagamento processado com segurança pelo Asaas.</p>
        </div>
        {error && <p className="mt-4 rounded-[16px] bg-[#f5c9c6] px-4 py-3 text-sm font-bold text-[#8f1f18]">{error}</p>}
        <button disabled={loading} onClick={subscribe} className="action-label pressable mt-6 flex min-h-14 w-full items-center justify-center gap-2 rounded-full bg-[var(--accent)] px-5 font-black uppercase text-black disabled:opacity-60">
          {loading ? "Abrindo pagamento..." : "Assinar agora"}<ArrowRight size={20} weight="bold"/>
        </button>
        <p className="mt-4 text-center text-xs font-semibold text-[#666]">Você será direcionado para a página segura de pagamento do Asaas.</p>
      </section>
    </main>
  );
}
