import React from "react";
import {
  Trash2,
  X,
} from "lucide-react";
import {
  Currency,
  CustomOptionGroup,
  EventDetails,
  MenuCategory,
  MenuItem,
  MenuPricingType,
  NotificationTrigger,
  TicketOrder,
} from "../../types";

type Props = {
  eventDetails: EventDetails;
  formatMoney: (amount: number, currency: Currency) => string;

  // Dish Add/Edit Modal
  isDishModalOpen: boolean;
  editingItem: MenuItem | null;
  dishName: string;
  setDishName: (v: string) => void;
  dishCategory: MenuCategory;
  setDishCategory: (v: MenuCategory) => void;
  dishPricingType: MenuPricingType;
  setDishPricingType: (v: MenuPricingType) => void;
  dishDescription: string;
  setDishDescription: (v: string) => void;
  dishPriceUSD: string;
  setDishPriceUSD: (v: string) => void;
  dishPriceJMD: string;
  setDishPriceJMD: (v: string) => void;
  dishImage: string;
  setDishImage: (v: string) => void;
  dishStock: string;
  setDishStock: (v: string) => void;
  dishOptionGroups: CustomOptionGroup[];
  onDishImageUpload: (e: React.ChangeEvent<HTMLInputElement>) => void;
  onAddOptionGroup: () => void;
  onUpdateOptionGroupTitle: (groupId: string, title: string) => void;
  onRemoveOptionGroup: (groupId: string) => void;
  onAddChoiceToGroup: (groupId: string) => void;
  onUpdateChoiceInGroup: (
    groupId: string,
    index: number,
    field: "label" | "extraPriceUSD" | "extraPriceJMD",
    val: string
  ) => void;
  onRemoveChoiceFromGroup: (groupId: string, index: number) => void;
  onSaveDish: (e: React.FormEvent) => void;
  onCloseDishModal: () => void;

  // Custom Notification Modal
  customModalOrder: TicketOrder | null;
  customChannel: "sms" | "email" | "whatsapp";
  setCustomChannel: (c: "sms" | "email" | "whatsapp") => void;
  customTrigger: NotificationTrigger;
  setCustomTrigger: (t: NotificationTrigger) => void;
  customMessage: string;
  setCustomMessage: (m: string) => void;
  onSendCustomSubmit: (e: React.FormEvent) => void;
  onCloseCustomModal: () => void;

  // Cash Receipt Modal
  cashReceiptModalOrder: TicketOrder | null;
  modalCashCollector: string;
  setModalCashCollector: (v: string) => void;
  modalCashNotes: string;
  setModalCashNotes: (v: string) => void;
  onConfirmModalCashReceipt: () => void;
  onCloseCashReceiptModal: () => void;

  // Reject Payment Modal
  rejectModalOrder: TicketOrder | null;
  rejectReasonText: string;
  setRejectReasonText: (v: string) => void;
  onConfirmRejectOrder: () => void;
  onCloseRejectModal: () => void;

  // Partial Payment Modal
  partialModalOrder: TicketOrder | null;
  partialAmountInput: string;
  setPartialAmountInput: (v: string) => void;
  partialNotesInput: string;
  setPartialNotesInput: (v: string) => void;
  onConfirmPartialOrder: () => void;
  onClosePartialModal: () => void;

  // Unpublish & Cancel Event Modals
  unpublishModalOpen: boolean;
  onConfirmUnpublishEvent: () => void;
  onCloseUnpublishModal: () => void;
  cancelEventModalOpen: boolean;
  onConfirmCancelEvent: () => void;
  onCloseCancelEventModal: () => void;
};

export function HostModals({
  eventDetails,
  formatMoney,
  isDishModalOpen,
  editingItem,
  dishName,
  setDishName,
  dishCategory,
  setDishCategory,
  dishPricingType,
  setDishPricingType,
  dishDescription,
  setDishDescription,
  dishPriceUSD,
  setDishPriceUSD,
  dishPriceJMD,
  setDishPriceJMD,
  dishImage,
  setDishImage,
  dishStock,
  setDishStock,
  dishOptionGroups,
  onDishImageUpload,
  onAddOptionGroup,
  onUpdateOptionGroupTitle,
  onRemoveOptionGroup,
  onAddChoiceToGroup,
  onUpdateChoiceInGroup,
  onRemoveChoiceFromGroup,
  onSaveDish,
  onCloseDishModal,
  customModalOrder,
  customChannel,
  setCustomChannel,
  customTrigger,
  setCustomTrigger,
  customMessage,
  setCustomMessage,
  onSendCustomSubmit,
  onCloseCustomModal,
  cashReceiptModalOrder,
  modalCashCollector,
  setModalCashCollector,
  modalCashNotes,
  setModalCashNotes,
  onConfirmModalCashReceipt,
  onCloseCashReceiptModal,
  rejectModalOrder,
  rejectReasonText,
  setRejectReasonText,
  onConfirmRejectOrder,
  onCloseRejectModal,
  partialModalOrder,
  partialAmountInput,
  setPartialAmountInput,
  partialNotesInput,
  setPartialNotesInput,
  onConfirmPartialOrder,
  onClosePartialModal,
  unpublishModalOpen,
  onConfirmUnpublishEvent,
  onCloseUnpublishModal,
  cancelEventModalOpen,
  onConfirmCancelEvent,
  onCloseCancelEventModal,
}: Props) {
  const overlayStyle: React.CSSProperties = {
    position: "fixed",
    inset: 0,
    background: "rgba(2, 6, 23, 0.8)",
    backdropFilter: "blur(6px)",
    zIndex: 100,
    display: "grid",
    placeItems: "center",
    padding: "16px",
  };

  const modalCardStyle: React.CSSProperties = {
    width: "min(560px, 100%)",
    maxHeight: "90vh",
    overflowY: "auto",
    background: "#0F172A",
    border: "1px solid rgba(148, 163, 184, 0.3)",
    borderRadius: "16px",
    padding: "22px",
    color: "#F8FAFC",
    boxShadow: "0 24px 60px rgba(0, 0, 0, 0.7)",
  };

  const inputStyle: React.CSSProperties = {
    width: "100%",
    height: "38px",
    padding: "0 12px",
    borderRadius: "8px",
    background: "#1E293B",
    border: "1px solid rgba(148, 163, 184, 0.35)",
    color: "#F8FAFC",
    fontSize: "12px",
  };

  return (
    <>
      {/* 1. Dish Add / Edit Modal */}
      {isDishModalOpen && (
        <div style={overlayStyle}>
          <div style={modalCardStyle}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "14px" }}>
              <h3 style={{ margin: 0, fontSize: "17px", fontWeight: 800 }}>
                {editingItem ? `Edit Dish: ${editingItem.name}` : "Add New Menu Dish"}
              </h3>
              <button
                type="button"
                onClick={onCloseDishModal}
                style={{ background: "none", border: "none", color: "#94A3B8" }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={onSaveDish} style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
              <div>
                <label style={{ display: "block", fontSize: "11px", fontWeight: 700, marginBottom: "4px" }}>
                  Dish Name *
                </label>
                <input
                  type="text"
                  required
                  value={dishName}
                  onChange={(e) => setDishName(e.target.value)}
                  style={inputStyle}
                />
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "10px" }}>
                <div>
                  <label style={{ display: "block", fontSize: "11px", fontWeight: 700, marginBottom: "4px" }}>
                    Category
                  </label>
                  <select
                    value={dishCategory}
                    onChange={(e) => setDishCategory(e.target.value as MenuCategory)}
                    style={inputStyle}
                  >
                    <option value="Mains">Mains</option>
                    <option value="Food">Food</option>
                    <option value="Sides & Extras">Sides & Extras</option>
                    <option value="Sides">Sides</option>
                    <option value="Beverages">Beverages</option>
                    <option value="Drinks">Drinks</option>
                    <option value="Snacks">Snacks</option>
                    <option value="Desserts">Desserts</option>
                    <option value="Community Donations">Community Donations</option>
                  </select>
                </div>
                <div>
                  <label style={{ display: "block", fontSize: "11px", fontWeight: 700, marginBottom: "4px" }}>
                    Pricing Type
                  </label>
                  <select
                    value={dishPricingType}
                    onChange={(e) => setDishPricingType(e.target.value as MenuPricingType)}
                    style={inputStyle}
                  >
                    <option value="fixed">Fixed Price</option>
                    <option value="customizable">Customizable (Option Groups)</option>
                    <option value="custom_amount">Custom Donation Amount</option>
                  </select>
                </div>
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: "10px" }}>
                <div>
                  <label style={{ display: "block", fontSize: "11px", fontWeight: 700, marginBottom: "4px" }}>
                    Price JMD (J$)
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={dishPriceJMD}
                    onChange={(e) => setDishPriceJMD(e.target.value)}
                    style={inputStyle}
                  />
                </div>
                <div>
                  <label style={{ display: "block", fontSize: "11px", fontWeight: 700, marginBottom: "4px" }}>
                    Price USD ($)
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={dishPriceUSD}
                    onChange={(e) => setDishPriceUSD(e.target.value)}
                    style={inputStyle}
                  />
                </div>
                <div>
                  <label style={{ display: "block", fontSize: "11px", fontWeight: 700, marginBottom: "4px" }}>
                    Stock Limit
                  </label>
                  <input
                    type="number"
                    value={dishStock}
                    onChange={(e) => setDishStock(e.target.value)}
                    style={inputStyle}
                  />
                </div>
              </div>

              <div>
                <label style={{ display: "block", fontSize: "11px", fontWeight: 700, marginBottom: "4px" }}>
                  Description
                </label>
                <textarea
                  rows={2}
                  value={dishDescription}
                  onChange={(e) => setDishDescription(e.target.value)}
                  style={{ ...inputStyle, height: "auto", padding: "8px 12px" }}
                />
              </div>

              <div style={{ display: "flex", gap: "8px", alignItems: "center" }}>
                <input
                  type="text"
                  placeholder="Image URL"
                  value={dishImage}
                  onChange={(e) => setDishImage(e.target.value)}
                  style={inputStyle}
                />
                <label
                  style={{
                    padding: "8px 12px",
                    borderRadius: "8px",
                    background: "rgba(99, 102, 241, 0.2)",
                    border: "1px solid rgba(129, 140, 248, 0.4)",
                    color: "#A5B4FC",
                    fontSize: "11px",
                    fontWeight: 700,
                    cursor: "pointer",
                    whiteSpace: "nowrap",
                  }}
                >
                  Upload Photo
                  <input
                    id="menu-photo-input"
                    type="file"
                    accept="image/*"
                    onChange={onDishImageUpload}
                    style={{ display: "none" }}
                  />
                </label>
              </div>

              {dishPricingType === "customizable" && (
                <div style={{ display: "flex", flexDirection: "column", gap: "8px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                    <strong style={{ fontSize: "12px" }}>Option Groups ({dishOptionGroups.length})</strong>
                    <button
                      type="button"
                      onClick={onAddOptionGroup}
                      style={{
                        padding: "4px 10px",
                        borderRadius: "6px",
                        background: "rgba(16, 185, 129, 0.16)",
                        color: "#34D399",
                        border: "1px solid rgba(52, 211, 153, 0.35)",
                        fontSize: "10px",
                        fontWeight: 700,
                      }}
                    >
                      + Add Option Group
                    </button>
                  </div>
                  {dishOptionGroups.map((group) => (
                    <div
                      key={group.id}
                      style={{
                        padding: "10px",
                        borderRadius: "8px",
                        background: "#0B1120",
                        border: "1px solid rgba(148, 163, 184, 0.2)",
                      }}
                    >
                      <div style={{ display: "flex", gap: "8px", marginBottom: "8px" }}>
                        <input
                          type="text"
                          value={group.title}
                          onChange={(e) => onUpdateOptionGroupTitle(group.id, e.target.value)}
                          style={{ ...inputStyle, height: "32px" }}
                        />
                        <button
                          type="button"
                          onClick={() => onRemoveOptionGroup(group.id)}
                          style={{
                            padding: "4px 8px",
                            borderRadius: "6px",
                            background: "rgba(239, 68, 68, 0.2)",
                            color: "#FCA5A5",
                            border: "none",
                          }}
                        >
                          <Trash2 size={12} />
                        </button>
                      </div>
                      {group.choices.map((ch, idx) => (
                        <div key={idx} style={{ display: "flex", gap: "6px", marginBottom: "6px" }}>
                          <input
                            type="text"
                            value={ch.label}
                            onChange={(e) =>
                              onUpdateChoiceInGroup(group.id, idx, "label", e.target.value)
                            }
                            style={{ ...inputStyle, height: "30px" }}
                          />
                          <button
                            type="button"
                            onClick={() => onRemoveChoiceFromGroup(group.id, idx)}
                            style={{
                              padding: "4px 6px",
                              borderRadius: "5px",
                              background: "#334155",
                              color: "#CBD5E1",
                              border: "none",
                            }}
                          >
                            <X size={11} />
                          </button>
                        </div>
                      ))}
                      <button
                        type="button"
                        onClick={() => onAddChoiceToGroup(group.id)}
                        style={{
                          padding: "3px 8px",
                          borderRadius: "5px",
                          background: "#1E293B",
                          color: "#A5B4FC",
                          border: "1px solid rgba(129, 140, 248, 0.3)",
                          fontSize: "10px",
                        }}
                      >
                        + Add Choice
                      </button>
                    </div>
                  ))}
                </div>
              )}

              <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px", marginTop: "8px" }}>
                <button
                  type="button"
                  onClick={onCloseDishModal}
                  style={{
                    padding: "8px 14px",
                    borderRadius: "8px",
                    background: "#334155",
                    color: "#CBD5E1",
                    border: "none",
                    fontSize: "12px",
                    fontWeight: 700,
                  }}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  style={{
                    padding: "8px 16px",
                    borderRadius: "8px",
                    background: "#10B981",
                    color: "#052E16",
                    border: "none",
                    fontSize: "12px",
                    fontWeight: 800,
                  }}
                >
                  Save Dish
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 2. Custom Notification Modal */}
      {customModalOrder && (
        <div style={overlayStyle}>
          <div style={modalCardStyle}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
              <h3 style={{ margin: 0, fontSize: "16px", fontWeight: 800 }}>
                Send Notification to {customModalOrder.customerName}
              </h3>
              <button type="button" onClick={onCloseCustomModal} style={{ background: "none", border: "none", color: "#94A3B8" }}>
                <X size={18} />
              </button>
            </div>
            <form onSubmit={onSendCustomSubmit} style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "10px" }}>
                <select
                  value={customChannel}
                  onChange={(e) => setCustomChannel(e.target.value as "sms" | "email" | "whatsapp")}
                  style={inputStyle}
                >
                  <option value="sms">SMS (+1 U.S. number)</option>
                  <option value="email">Transactional Email</option>
                  <option value="whatsapp">WhatsApp Cloud</option>
                </select>
                <select
                  value={customTrigger}
                  onChange={(e) => setCustomTrigger(e.target.value as NotificationTrigger)}
                  style={inputStyle}
                >
                  <option value="pickup_instructions">Pickup Instructions</option>
                  <option value="payment_instructions">Payment Instructions</option>
                  <option value="event_reminders">Event Reminder</option>
                </select>
              </div>
              <textarea
                rows={4}
                required
                value={customMessage}
                onChange={(e) => setCustomMessage(e.target.value)}
                style={{ ...inputStyle, height: "auto", padding: "10px 12px" }}
              />
              <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px" }}>
                <button
                  type="button"
                  onClick={onCloseCustomModal}
                  style={{
                    padding: "8px 14px",
                    borderRadius: "8px",
                    background: "#334155",
                    color: "#CBD5E1",
                    border: "none",
                    fontSize: "12px",
                  }}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  style={{
                    padding: "8px 16px",
                    borderRadius: "8px",
                    background: "#4F46E5",
                    color: "#FFFFFF",
                    border: "none",
                    fontSize: "12px",
                    fontWeight: 800,
                  }}
                >
                  Send Message
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* 3. Official Cash Receipt Modal */}
      {cashReceiptModalOrder && (
        <div style={overlayStyle}>
          <div style={modalCardStyle}>
            <h3 style={{ margin: "0 0 8px", fontSize: "16px", fontWeight: 800 }}>
              Record Official Cash Receipt — {cashReceiptModalOrder.orderNumber}
            </h3>
            <p style={{ margin: "0 0 12px", fontSize: "12px", color: "#94A3B8" }}>
              Customer: <strong>{cashReceiptModalOrder.customerName}</strong> · Amount:{" "}
              <strong style={{ color: "#34D399" }}>
                {formatMoney(cashReceiptModalOrder.totalAmount, cashReceiptModalOrder.currency)}
              </strong>
            </p>
            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
              <input
                type="text"
                placeholder="Authorized Collector Name"
                value={modalCashCollector}
                onChange={(e) => setModalCashCollector(e.target.value)}
                style={inputStyle}
              />
              <input
                type="text"
                placeholder="Cash receipt audit notes"
                value={modalCashNotes}
                onChange={(e) => setModalCashNotes(e.target.value)}
                style={inputStyle}
              />
              <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px", marginTop: "6px" }}>
                <button
                  type="button"
                  onClick={onCloseCashReceiptModal}
                  style={{
                    padding: "8px 14px",
                    borderRadius: "8px",
                    background: "#334155",
                    color: "#CBD5E1",
                    border: "none",
                    fontSize: "12px",
                  }}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={onConfirmModalCashReceipt}
                  style={{
                    padding: "8px 16px",
                    borderRadius: "8px",
                    background: "#10B981",
                    color: "#052E16",
                    border: "none",
                    fontSize: "12px",
                    fontWeight: 800,
                  }}
                >
                  Confirm Cash Receipt & Activate Ticket
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* 4. Reject Payment Modal */}
      {rejectModalOrder && (
        <div style={overlayStyle}>
          <div style={modalCardStyle}>
            <h3 style={{ margin: "0 0 8px", fontSize: "16px", fontWeight: 800, color: "#FCA5A5" }}>
              Reject Payment Submission — {rejectModalOrder.orderNumber}
            </h3>
            <textarea
              rows={3}
              placeholder="Enter reason for rejection (e.g. Reference not found on NCB statement)..."
              value={rejectReasonText}
              onChange={(e) => setRejectReasonText(e.target.value)}
              style={{ ...inputStyle, height: "auto", padding: "10px 12px", marginBottom: "12px" }}
            />
            <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px" }}>
              <button
                type="button"
                onClick={onCloseRejectModal}
                style={{
                  padding: "8px 14px",
                  borderRadius: "8px",
                  background: "#334155",
                  color: "#CBD5E1",
                  border: "none",
                  fontSize: "12px",
                }}
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={onConfirmRejectOrder}
                style={{
                  padding: "8px 16px",
                  borderRadius: "8px",
                  background: "#EF4444",
                  color: "#FFFFFF",
                  border: "none",
                  fontSize: "12px",
                  fontWeight: 800,
                }}
              >
                Confirm Rejection
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 5. Partial Payment Modal */}
      {partialModalOrder && (
        <div style={overlayStyle}>
          <div style={modalCardStyle}>
            <h3 style={{ margin: "0 0 8px", fontSize: "16px", fontWeight: 800 }}>
              Record Partial Deposit — {partialModalOrder.orderNumber}
            </h3>
            <p style={{ margin: "0 0 12px", fontSize: "12px", color: "#94A3B8" }}>
              Order Total: {formatMoney(partialModalOrder.totalAmount, partialModalOrder.currency)}. Only the verified deposit amount is counted as confirmed revenue.
            </p>
            <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
              <input
                type="number"
                step="0.01"
                placeholder="Verified deposit amount received"
                value={partialAmountInput}
                onChange={(e) => setPartialAmountInput(e.target.value)}
                style={inputStyle}
              />
              <input
                type="text"
                placeholder="Verification notes"
                value={partialNotesInput}
                onChange={(e) => setPartialNotesInput(e.target.value)}
                style={inputStyle}
              />
              <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px", marginTop: "6px" }}>
                <button
                  type="button"
                  onClick={onClosePartialModal}
                  style={{
                    padding: "8px 14px",
                    borderRadius: "8px",
                    background: "#334155",
                    color: "#CBD5E1",
                    border: "none",
                    fontSize: "12px",
                  }}
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={onConfirmPartialOrder}
                  style={{
                    padding: "8px 16px",
                    borderRadius: "8px",
                    background: "#38BDF8",
                    color: "#082F49",
                    border: "none",
                    fontSize: "12px",
                    fontWeight: 800,
                  }}
                >
                  Record Partial Deposit
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* 6. Unpublish Event Modal */}
      {unpublishModalOpen && (
        <div style={overlayStyle}>
          <div style={modalCardStyle}>
            <h3 style={{ margin: "0 0 8px", fontSize: "16px", fontWeight: 800, color: "#FBBF24" }}>
              Unpublish Event & Pause Ordering?
            </h3>
            <p style={{ margin: "0 0 14px", fontSize: "12px", color: "#CBD5E1" }}>
              Existing verified tickets remain active, while new guest orders are paused until you publish again.
            </p>
            <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px" }}>
              <button
                type="button"
                onClick={onCloseUnpublishModal}
                style={{
                  padding: "8px 14px",
                  borderRadius: "8px",
                  background: "#334155",
                  color: "#CBD5E1",
                  border: "none",
                  fontSize: "12px",
                }}
              >
                Keep Published
              </button>
              <button
                type="button"
                onClick={onConfirmUnpublishEvent}
                style={{
                  padding: "8px 16px",
                  borderRadius: "8px",
                  background: "#F59E0B",
                  color: "#090D16",
                  border: "none",
                  fontSize: "12px",
                  fontWeight: 800,
                }}
              >
                Confirm Unpublish
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 7. Cancel Event Modal */}
      {cancelEventModalOpen && (
        <div style={overlayStyle}>
          <div style={modalCardStyle}>
            <h3 style={{ margin: "0 0 8px", fontSize: "16px", fontWeight: 800, color: "#FCA5A5" }}>
              Cancel Event & Halt Box Office?
            </h3>
            <p style={{ margin: "0 0 14px", fontSize: "12px", color: "#CBD5E1" }}>
              This marks {eventDetails.title} as CANCELLED and halts all new ticket sales.
            </p>
            <div style={{ display: "flex", justifyContent: "flex-end", gap: "8px" }}>
              <button
                type="button"
                onClick={onCloseCancelEventModal}
                style={{
                  padding: "8px 14px",
                  borderRadius: "8px",
                  background: "#334155",
                  color: "#CBD5E1",
                  border: "none",
                  fontSize: "12px",
                }}
              >
                Go Back
              </button>
              <button
                type="button"
                onClick={onConfirmCancelEvent}
                style={{
                  padding: "8px 16px",
                  borderRadius: "8px",
                  background: "#EF4444",
                  color: "#FFFFFF",
                  border: "none",
                  fontSize: "12px",
                  fontWeight: 800,
                }}
              >
                Confirm Event Cancellation
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
