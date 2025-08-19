// state/selection_state.js
export class Selection_State {
  constructor() {
    this.active = false
    this.hover_target = null
    this.selected_target = null
  }

  set_active(on) { this.active = !!on }
  set_hover_target(el) { this.hover_target = el }
  set_selected_target(el) { this.selected_target = el }
}

