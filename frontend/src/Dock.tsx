import { motion, useMotionValue, useSpring, useTransform } from "motion/react";
import { ReactNode, useRef } from "react";

export type DockItem = { icon: ReactNode; label: string; onClick: () => void; className?: string };

export default function Dock({ items, className = "" }: { items: DockItem[]; className?: string }) {
  const mouseX = useMotionValue(Infinity);
  return <div className={`dock-outer ${className}`}><motion.nav className="dock-panel" onMouseMove={e => mouseX.set(e.clientX)} onMouseLeave={() => mouseX.set(Infinity)} aria-label="Navegação principal">
    {items.map((item, index) => <DockItem key={index} item={item} mouseX={mouseX} />)}
  </motion.nav></div>;
}

function DockItem({ item, mouseX }: { item: DockItem; mouseX: ReturnType<typeof useMotionValue<number>> }) {
  const ref = useRef<HTMLButtonElement>(null);
  const distance = useTransform(mouseX, x => {
    const rect = ref.current?.getBoundingClientRect();
    return rect ? x - rect.left - rect.width / 2 : Infinity;
  });
  const size = useSpring(useTransform(distance, [-180, 0, 180], [50, 70, 50]), { mass: 0.1, stiffness: 150, damping: 12 });
  return <motion.button ref={ref} type="button" className={`dock-item ${item.className ?? ""}`} style={{ width: size, height: size }} onClick={item.onClick} aria-label={item.label}>
    <span className="dock-icon">{item.icon}</span><span className="dock-label">{item.label}</span>
  </motion.button>;
}
